<?php declare(strict_types=1);

namespace PartialDelivery\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use PartialDelivery\Core\Content\PartialDelivery\PartialDeliveryEntity;

#[Route(defaults: ['_routeScope' => ['api']])]
class PartialDeliveryController extends AbstractController
{
    private Connection $connection;
    private EntityRepository $partialDeliveryRepository;
    private EntityRepository $orderDeliveryPosition;
    private EntityRepository $orderLineItemRepository;
    private EntityRepository $orderRepository;
    private EntityRepository $orderDeliveryRepository;

    public function __construct(
        Connection $connection,
        EntityRepository $partialDeliveryRepository,
        EntityRepository $orderDeliveryPosition,
        EntityRepository $orderLineItemRepository,
        EntityRepository $orderRepository,
        EntityRepository $orderDeliveryRepository
    ) {
        $this->connection = $connection;
        $this->partialDeliveryRepository = $partialDeliveryRepository;
        $this->orderDeliveryPosition = $orderDeliveryPosition;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    #[Route(path: '/api/_action/partial-shipment-delivery/delete/{id}', name: 'api.partial_shipment_delivery.delete', methods: ['POST'])]
    public function delete(string $id, Context $context): JsonResponse
    {
        try {
            $binaryId = Uuid::fromHexToBytes($id);

            $affectedRows = $this->connection->delete('partial_delivery', ['id' => $binaryId]);

            if ($affectedRows === 0) {
                return new JsonResponse(['error' => 'Partial delivery not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            return new JsonResponse(['message' => 'Partial delivery deleted successfully.'], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/api/_action/partial-shipment-delivery/update/{id}', name: 'api.partial_shipment_delivery.update', methods: ['PATCH'])]
    public function update(string $id, Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
    
        if (!isset($data['partialDeliveries']) || !is_array($data['partialDeliveries'])) {
            return new JsonResponse(['error' => 'Invalid request format. Expected "partialDeliveries" array.'], JsonResponse::HTTP_BAD_REQUEST);
        }
    
        $updatedIds = [];
        $skippedItems = [];
    
        foreach ($data['partialDeliveries'] as $index => $delivery) {
            if (empty($delivery['orderLineItemId']) || !Uuid::isValid($delivery['orderLineItemId'])) {
                $skippedItems[] = [
                    'index' => $index,
                    'orderLineItemId' => $delivery['orderLineItemId'] ?? null,
                    'reason' => 'Invalid or missing orderLineItemId',
                ];
                continue;
            }
    
            if (!isset($delivery['quantity'], $delivery['package'], $delivery['trackingCode'])) {
                $skippedItems[] = [
                    'index' => $index,
                    'orderLineItemId' => $delivery['orderLineItemId'],
                    'reason' => 'Missing required fields for partial delivery',
                ];
                continue;
            }
    
            $orderLineItemId = $delivery['orderLineItemId'];
            $shipmentQuantity = (int)$delivery['quantity'];
    
            $criteria = new Criteria([$orderLineItemId]);
            $criteria->addAssociation('order');
            
            $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
            
            if (
                !$orderLineItem ||
                $orderLineItem->get('type') !== 'product' || 
                $orderLineItem->get('productId') === null    
            ) {
                $skippedItems[] = [
                    'index' => $index,
                    'orderLineItemId' => $orderLineItemId,
                    'reason' => 'Line item is not a real product (discount/promotion/custom item)',
                ];
                continue;
            }
            
    
            $orderedQuantity = (int)$orderLineItem->getQuantity();
    
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderLineItemId', $orderLineItemId));
    
            $shippedDeliveries = $this->partialDeliveryRepository->search($criteria, $context)->getEntities();
    
            $alreadyShipped = 0;
            foreach ($shippedDeliveries as $shipment) {
                if ($shipment->getId() !== $id) {
                    $alreadyShipped += $shipment->getQuantity();
                }
            }
    
            $remainingQuantity = $orderedQuantity - $alreadyShipped;
    
            if ($shipmentQuantity > $remainingQuantity) {
                $skippedItems[] = [
                    'index' => $index,
                    'orderLineItemId' => $orderLineItemId,
                    'reason' => "Cannot ship $shipmentQuantity. Only $remainingQuantity remaining.",
                ];
                continue;
            }
    
            $binaryId = Uuid::fromHexToBytes($id);
    
            try {
                $fieldsToUpdate = [
                    'quantity' => $shipmentQuantity,
                    'package' => $delivery['package'],
                    'tracking_code' => $delivery['trackingCode'],
                ];
    
                $affectedRows = $this->connection->update('partial_delivery', $fieldsToUpdate, ['id' => $binaryId]);
    
                if ($affectedRows > 0) {
                    $updatedIds[] = $id;
                }
            } catch (\Exception $e) {
                return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
    
        return new JsonResponse([
            'message' => count($updatedIds) > 0
                ? 'Partial deliveries updated successfully'
                : 'No shipments were updated.',
            'updatedIds' => $updatedIds,
            'skippedItems' => $skippedItems,
        ], JsonResponse::HTTP_OK);
    }
    
    
    #[Route(path: '/api/_action/partial-shipment-delivery', name: 'api.partial_shipment_delivery.create', methods: ['POST'])]
    public function create(Request $request, Context $context): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
    
        if (!isset($data['partialDeliveries']) || !is_array($data['partialDeliveries'])) {
            return new JsonResponse(['error' => 'Invalid request format. Expected "partialDeliveries" array.'], JsonResponse::HTTP_BAD_REQUEST);
        }
    
        $insertedIds = [];
        $skippedItems = [];
        $staticList = [];
    
        try {
            $firstLineItemId = $data['partialDeliveries'][0]['orderLineItemId'] ?? null;
            if (!$firstLineItemId || !Uuid::isValid($firstLineItemId)) {
                return new JsonResponse(['error' => 'Invalid or missing first orderLineItemId'], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            $criteria = new Criteria([$firstLineItemId]);
            $criteria->addAssociation('order');
    
            $firstLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
            if (!$firstLineItem || !$firstLineItem->getOrderId()) {
                return new JsonResponse(['error' => 'Unable to resolve order from order line item.'], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            $orderCriteria = new Criteria([$firstLineItem->getOrderId()]);
            $orderCriteria->addAssociation('lineItems');
            $order = $this->orderRepository->search($orderCriteria, $context)->first();
    
            $requestLineItemMap = [];
            foreach ($data['partialDeliveries'] as $delivery) {
                if (!empty($delivery['orderLineItemId'])) {
                    $requestLineItemMap[$delivery['orderLineItemId']] = $delivery;
                }
            }
    
            foreach ($order->getLineItems() as $item) {
                if (
                    $item->getType() !== 'product' ||
                    $item->get('productId') === null
                ) {
                    continue;
                }
            
                $lineItemId = $item->getId();
            
                $shipmentCriteria = new Criteria();
                $shipmentCriteria->addFilter(new EqualsFilter('orderLineItemId', $lineItemId));
                $existingShipments = $this->partialDeliveryRepository->search($shipmentCriteria, $context);
            
                $hasShipment = $existingShipments->count() > 0;
            
                if (!array_key_exists($lineItemId, $requestLineItemMap) && !$hasShipment) {
                    $staticList[] = [
                        'orderLineItemId' => $lineItemId,
                        'quantity' => 0,
                        'package' => 'NO_PACKAGE',
                        'trackingCode' => 'NO_TRACKING',
                    ];
                }
            }
            
    
            foreach ($data['partialDeliveries'] as $index => $delivery) {
                if (!is_array($delivery)) {
                    return new JsonResponse(['error' => "Expected partialDelivery at index $index to be an associative array."], JsonResponse::HTTP_BAD_REQUEST);
                }
    
                if (empty($delivery['orderLineItemId']) || !Uuid::isValid($delivery['orderLineItemId'])) {
                    $skippedItems[] = [
                        'index' => $index,
                        'orderLineItemId' => $delivery['orderLineItemId'],
                        'reason' => 'Invalid or missing orderLineItemId',
                    ];
                    continue;
                }
    
                if (!isset($delivery['quantity'], $delivery['package'], $delivery['trackingCode'])) {
                    $skippedItems[] = [
                        'index' => $index,
                        'orderLineItemId' => $delivery['orderLineItemId'],
                        'reason' => 'Missing required fields',
                    ];
                    continue;
                }
    
                if ((int)$delivery['quantity'] <= 0) {
                    continue;
                }
    
                $orderLineItemId = $delivery['orderLineItemId'];
                $shipmentQuantity = (int)$delivery['quantity'];
    
                $criteria = new Criteria([$orderLineItemId]);
                $criteria->addAssociation('order');
    
                $orderLineItem = $this->orderLineItemRepository->search($criteria, $context)->first();
    
                if (!$orderLineItem) {
                    $skippedItems[] = [
                        'index' => $index,
                        'orderLineItemId' => $orderLineItemId,
                        'reason' => 'Order line item not found',
                    ];
                    continue;
                }
    
                $orderedQuantity = (int)$orderLineItem->getQuantity();
    
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('orderLineItemId', $orderLineItemId));
    
                $shippedDeliveries = $this->partialDeliveryRepository->search($criteria, $context)->getEntities();
    
                $alreadyShipped = array_sum(array_map(
                    fn (PartialDeliveryEntity $shipment) => $shipment->getQuantity(),
                    $shippedDeliveries->getElements()
                ));
    
                $remainingQuantity = $orderedQuantity - $alreadyShipped;
    
                if ($shipmentQuantity > $remainingQuantity) {
                    $skippedItems[] = [
                        'index' => $index,
                        'orderLineItemId' => $orderLineItemId,
                        'reason' => "Cannot ship $shipmentQuantity. Only $remainingQuantity remaining.",
                    ];
                    continue;
                }
    
                    $this->connection->executeStatement(
                        'DELETE FROM partial_delivery WHERE order_line_item_id = :id AND quantity = 0 AND package = :pkg AND tracking_code = :tc',
                        [
                            'id' => $orderLineItemId,
                            'pkg' => 'NO_PACKAGE',
                            'tc' => 'NO_TRACKING',
                        ]
                    );

                    $this->connection->insert('partial_delivery', [
                        'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
                        'order_line_item_id' => $orderLineItemId,
                        'quantity' => $shipmentQuantity,
                        'package' => $delivery['package'],
                        'tracking_code' => $delivery['trackingCode'],
                        'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
                    ]);

    
                $insertedIds[] = $orderLineItemId;
            }
    
            foreach ($staticList as $entry) {
                $this->connection->insert('partial_delivery', [
                    'id' => Uuid::fromHexToBytes(Uuid::randomHex()),
                    'order_line_item_id' => $entry['orderLineItemId'],
                    'quantity' => 0,
                    'package' => $entry['package'],
                    'tracking_code' => $entry['trackingCode'],
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
                ]);
            }
    
            return new JsonResponse([
                'insertedIds' => $insertedIds,
                'skippedItems' => $skippedItems,
                'staticList' => $staticList,
            ], JsonResponse::HTTP_CREATED);
    
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    
    #[Route(path: '/api/_action/partial-shipment-delivery/{id}', name: 'api.partial_shipment_delivery.get', methods: ['GET'])]
    public function get(string $id, Context $context): JsonResponse
    {
        try {
            if (!Uuid::isValid($id)) {
                return new JsonResponse(['error' => 'Invalid UUID format.'], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            $criteria = new Criteria([ $id ]);
            $criteria->addAssociation('orderLineItem');
    
            $partialDelivery = $this->partialDeliveryRepository->search($criteria, $context)->first();
    
            if (!$partialDelivery) {
                return new JsonResponse(['error' => 'Partial delivery not found.'], JsonResponse::HTTP_NOT_FOUND);
            }
    
            return new JsonResponse([
                'id' => $partialDelivery->getId(),
                'orderLineItemId' => $partialDelivery->getOrderLineItemId(),
                'quantity' => $partialDelivery->getQuantity(),
                'package' => $partialDelivery->getPackage(),
                'trackingCode' => $partialDelivery->getTrackingCode(),
                'createdAt' => $partialDelivery->getCreatedAt()?->format(\DateTime::ATOM),
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
}
