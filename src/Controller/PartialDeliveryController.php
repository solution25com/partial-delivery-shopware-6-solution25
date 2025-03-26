<?php declare(strict_types=1);

namespace PartialDelivery\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;


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
    )
    {
        $this->connection = $connection;
        $this->partialDeliveryRepository = $partialDeliveryRepository;
        $this->orderDeliveryPosition = $orderDeliveryPosition;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
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

        try {
            foreach ($data['partialDeliveries'] as $index => $delivery) {
                if (!is_array($delivery)) {
                    return new JsonResponse(['error' => "Expected partialDelivery at index $index to be an associative arrays."], JsonResponse::HTTP_BAD_REQUEST);
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
                $id = Uuid::fromHexToBytes(Uuid::randomHex());
                $orderLineItemId = ($delivery['orderLineItemId']);
                $shipmentQuantity = (int) $delivery['quantity'];

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

                $orderedQuantity = (int) $orderLineItem->getQuantity();

                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('orderLineItemId', $orderLineItemId));

                $shippedDeliveries = $this->partialDeliveryRepository->search($criteria, $context)->getEntities();

                $alreadyShipped = array_sum(array_map(fn ($shipment) => $shipment->getQuantity(), $shippedDeliveries->getElements()));

                $remainingQuantity = $orderedQuantity - $alreadyShipped;

                if ($shipmentQuantity > $remainingQuantity) {
                    $skippedItems[] = [
                        'index' => $index,
                        'orderLineItemId' => $orderLineItemId,
                        'reason' => "Cannot ship $shipmentQuantity. Only $remainingQuantity remaining from ordered quantity.",
                    ];
                    continue;
                }

                $this->connection->insert('partial_delivery', [
                    'id' => $id,
                    'order_line_item_id' => $orderLineItemId,
                    'quantity' => $delivery['quantity'],
                    'package' => $delivery['package'],
                    'tracking_code' => $delivery['trackingCode'],
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v')
                ]);

                $insertedIds[] = $orderLineItemId;
            }

            return new JsonResponse([
                'insertedIds' => $insertedIds,
                'skippedItems' => $skippedItems,
            ], JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse(['errorr' => $e->getMessage()], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[Route(path: '/api/_action/shipment/{orderId}', name: 'api.shipment.get', methods: ['GET'])]
    public function getShipments(string $orderId, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addAssociation('positions');

        $partialDeliveries = $this->orderDeliveryRepository->search($criteria, $context)->first();

        $lineItems = [];
        foreach ($partialDeliveries->getPositions()->getElements() as $position) {
            $lineItems[$position->getOrderLineItemId()] = [
                'lineItemId' => $position->getOrderLineItemId(),
                'quantityOrdered' => $position->getQuantity(),
                'shipments' => []
            ];
        }

        foreach ($lineItems as &$lineItem) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderLineItemId', $lineItem['lineItemId']));
            $partialDeliveriesShipments = $this->partialDeliveryRepository->search($criteria, $context)->getElements();

            foreach ($partialDeliveriesShipments as $partialDeliveriesShipment) {
                $lineItem['shipments'][] = [
                    'quantity' => $partialDeliveriesShipment->getQuantity(),
                    'package' => $partialDeliveriesShipment->getPackage(),
                    'trackingCode' => $partialDeliveriesShipment->getTrackingCode(),
                    'createdAt' => $partialDeliveriesShipment->getCreatedAt(),
                ];
            }
        }

        return new JsonResponse(array_values($lineItems));
    }
}
