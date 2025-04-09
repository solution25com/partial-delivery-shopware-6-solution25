<?php declare(strict_types=1);

namespace PartialDelivery\Core\Framework\Twig;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ShipmentsTwigExtension extends AbstractExtension
{
    private EntityRepository $orderDeliveryRepository;
    private EntityRepository $partialDeliveryRepository;

    public function __construct(
        EntityRepository $orderDeliveryRepository,
        EntityRepository $partialDeliveryRepository
    ) {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->partialDeliveryRepository = $partialDeliveryRepository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getShipmentsByOrderId', [$this, 'getShipmentsByOrderId']),
        ];
    }

    public function getShipmentsByOrderId(string $orderId): array
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addAssociation('positions');

        $orderDeliveries = $this->orderDeliveryRepository->search($criteria, $context)->first();

        if (!$orderDeliveries) {
            return [];
        }

        $lineItems = [];

        // Get ordered quantities from order deliveries
        foreach ($orderDeliveries->getPositions()->getElements() as $position) {
            $lineItems[$position->getOrderLineItemId()] = [
                'lineItemId' => $position->getOrderLineItemId(),
                'quantityOrdered' => $position->getQuantity(),
                'quantityShipped' => 0,  // Default to 0
                'quantityLeft' => $position->getQuantity(), // Initially, nothing is shipped
                'shipments' => []
            ];
        }

        // Fetch shipments and calculate shipped & left quantities
        foreach ($lineItems as &$lineItem) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderLineItemId', $lineItem['lineItemId']));
            $shipments = $this->partialDeliveryRepository->search($criteria, $context)->getElements();

            $totalShipped = 0;

            foreach ($shipments as $shipment) {
                $shipmentData = [
                    'quantity' => $shipment->getQuantity(),
                    'package' => $shipment->getPackage(),
                    'trackingCode' => $shipment->getTrackingCode(),
                    'createdAt' => $shipment->getCreatedAt(),
                ];

                $lineItem['shipments'][] = $shipmentData;
                $totalShipped += $shipment->getQuantity();
            }

            $lineItem['quantityShipped'] = $totalShipped;
            $lineItem['quantityLeft'] = max(0, $lineItem['quantityOrdered'] - $totalShipped);
        }

        return array_values($lineItems);
    }
}
