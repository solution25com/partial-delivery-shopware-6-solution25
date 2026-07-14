<?php

declare(strict_types=1);

namespace PartialDelivery\Service;

use PartialDelivery\Core\Content\PartialDelivery\PartialDeliveryEntity;
use PartialDelivery\Event\PartialDeliveryCaptureAdjustmentRequiredEvent;
use PartialDelivery\Event\PartialDeliveryCaptureCompletedEvent;
use PartialDelivery\Event\PartialDeliveryCaptureRequestedEvent;
use PartialDelivery\Event\PartialDeliveryCreatedEvent;
use PartialDelivery\Event\PartialDeliveryUpdatedEvent;
use PartialDelivery\Payment\PartialDeliveryCaptureRequest;
use PartialDelivery\Payment\PartialDeliveryCaptureResult;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Public, reusable entry point for the Partial Delivery plugin.
 *
 * External systems reach these use-cases through the HTTP controller; other
 * Shopware plugins (ERP connectors, payment providers) can inject this service
 * directly and call the same methods. All business logic lives here, not in the
 * controller. Persistence goes through the DAL repository (the Shopware 6 way).
 */
class PartialDeliveryService
{
    private const NO_PACKAGE = '';
    private const NO_TRACKING = '';

    private const CONFIG_CAPTURE_UPDATE_POLICY = 'PartialDelivery.config.captureUpdatePolicy';
    private const POLICY_PROTECT = 'protect';
    private const POLICY_FLAG = 'flag';

    public const STATUS_ADJUSTMENT_REQUIRED = 'adjustment_required';

    /**
     * Payment is committed or pending — such a row must not be edited financially
     * and must never be (re)captured.
     */
    private const CAPTURED_STATUSES = [
        PartialDeliveryCaptureResult::STATUS_CAPTURED,
        PartialDeliveryCaptureResult::STATUS_REQUESTED,
    ];

    /**
     * Statuses excluded from capture eligibility (already captured/pending, or flagged
     * for manual reconciliation). Everything else (null/open/failed/unsupported) is
     * still capturable, which makes repeated capture calls safe and incremental.
     */
    private const NON_CAPTURABLE_STATUSES = [
        PartialDeliveryCaptureResult::STATUS_CAPTURED,
        PartialDeliveryCaptureResult::STATUS_REQUESTED,
        self::STATUS_ADJUSTMENT_REQUIRED,
    ];

    public function __construct(
        private readonly EntityRepository $partialDeliveryRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $orderLineItemRepository,
        private readonly PartialDeliveryCaptureRegistry $captureRegistry,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Create one or more partial-delivery records.
     *
     * @param array<int, array<string, mixed>> $shipments Each item supports:
     *     orderLineItemId | (orderNumber|orderId)+productNumber, quantity (required),
     *     package?, trackingCode?, amount?, source?, externalReference?
     * @param array<string, mixed> $meta Optional shared metadata: source, externalReference
     *
     * @return array{success: bool, created: array<int, array<string, string>>, skipped: array<int, array<string, mixed>>}
     */
    public function createShipments(array $shipments, Context $context, array $meta = []): array
    {
        $created = [];
        $skipped = [];

        $defaultSource = isset($meta['source']) ? (string) $meta['source'] : null;
        $defaultExternalReference = isset($meta['externalReference']) ? (string) $meta['externalReference'] : null;

        foreach ($shipments as $index => $shipment) {
            if (!\is_array($shipment)) {
                $skipped[] = ['index' => $index, 'reason' => 'Each shipment must be an object.'];
                continue;
            }

            $quantity = isset($shipment['quantity']) ? (int) $shipment['quantity'] : 0;
            if ($quantity <= 0) {
                $skipped[] = ['index' => $index, 'reason' => 'Quantity must be greater than zero.'];
                continue;
            }

            $lineItem = $this->resolveLineItem($shipment, $context);
            if (!$lineItem instanceof OrderLineItemEntity) {
                $skipped[] = ['index' => $index, 'reason' => 'Order line item could not be resolved.'];
                continue;
            }

            if ($lineItem->getType() !== 'product' || $lineItem->getProductId() === null) {
                $skipped[] = [
                    'index' => $index,
                    'orderLineItemId' => $lineItem->getId(),
                    'reason' => 'Line item is not a real product (discount/promotion/custom item).',
                ];
                continue;
            }

            $remaining = $this->getRemainingQuantity($lineItem, $context);
            if ($quantity > $remaining) {
                $skipped[] = [
                    'index' => $index,
                    'orderLineItemId' => $lineItem->getId(),
                    'reason' => sprintf('Cannot deliver %d. Only %d remaining.', $quantity, $remaining),
                ];
                continue;
            }

            $id = Uuid::randomHex();

            $this->partialDeliveryRepository->create([[
                'id' => $id,
                'orderLineItemId' => $lineItem->getId(),
                'orderId' => $lineItem->getOrderId(),
                'quantity' => $quantity,
                'package' => isset($shipment['package']) ? (string) $shipment['package'] : self::NO_PACKAGE,
                'trackingCode' => isset($shipment['trackingCode']) ? (string) $shipment['trackingCode'] : self::NO_TRACKING,
                'amount' => isset($shipment['amount']) ? (float) $shipment['amount'] : null,
                'source' => isset($shipment['source']) ? (string) $shipment['source'] : $defaultSource,
                'externalReference' => isset($shipment['externalReference'])
                    ? (string) $shipment['externalReference']
                    : $defaultExternalReference,
            ]], $context);

            $entity = $this->partialDeliveryRepository->search(new Criteria([$id]), $context)->first();
            if ($entity instanceof PartialDeliveryEntity) {
                $this->eventDispatcher->dispatch(new PartialDeliveryCreatedEvent($entity, $context));
            }

            $created[] = ['orderLineItemId' => $lineItem->getId(), 'partialDeliveryId' => $id];
        }

        return ['success' => $skipped === [], 'created' => $created, 'skipped' => $skipped];
    }

    /**
     * Update an existing partial delivery via the DAL.
     *
     * If the row's payment was already captured and a financial field
     * (quantity/amount) changes, the configured capture-update policy applies:
     *  - protect: the change is rejected;
     *  - flag:    the change is applied, the row is marked `adjustment_required`
     *             and a PartialDeliveryCaptureAdjustmentRequiredEvent is dispatched
     *             (no money is moved automatically).
     *
     * @param array<string, mixed> $data Any of: quantity, package, trackingCode, amount, source, externalReference
     *
     * @return array{success: bool, partialDeliveryId: ?string, reason: ?string}
     */
    public function updateShipment(string $id, array $data, Context $context): array
    {
        if (!Uuid::isValid($id)) {
            return ['success' => false, 'partialDeliveryId' => null, 'reason' => 'Invalid partial delivery id.'];
        }

        $existing = $this->partialDeliveryRepository->search(new Criteria([$id]), $context)->first();
        if (!$existing instanceof PartialDeliveryEntity) {
            return ['success' => false, 'partialDeliveryId' => $id, 'reason' => 'Partial delivery not found.'];
        }

        $flagAdjustment = false;
        if ($this->isFinancialChange($existing, $data)
            && \in_array($existing->getPaymentStatus(), self::CAPTURED_STATUSES, true)
        ) {
            if ($this->getCaptureUpdatePolicy() === self::POLICY_PROTECT) {
                return [
                    'success' => false,
                    'partialDeliveryId' => $id,
                    'reason' => 'Payment already captured; changing quantity/amount requires a refund or '
                        . 'recapture and is blocked by the capture update policy (protect).',
                ];
            }

            $flagAdjustment = true;
        }

        $update = ['id' => $id];

        if (\array_key_exists('quantity', $data)) {
            $quantity = (int) $data['quantity'];
            if ($quantity <= 0) {
                return ['success' => false, 'partialDeliveryId' => $id, 'reason' => 'Quantity must be greater than zero.'];
            }

            $lineItem = $this->resolveLineItem(['orderLineItemId' => $existing->getOrderLineItemId()], $context);
            if (!$lineItem instanceof OrderLineItemEntity) {
                return ['success' => false, 'partialDeliveryId' => $id, 'reason' => 'Order line item could not be resolved.'];
            }

            $remaining = $this->getRemainingQuantity($lineItem, $context, $id);
            if ($quantity > $remaining) {
                return [
                    'success' => false,
                    'partialDeliveryId' => $id,
                    'reason' => sprintf('Cannot deliver %d. Only %d remaining.', $quantity, $remaining),
                ];
            }

            $update['quantity'] = $quantity;
        }

        foreach (['package', 'trackingCode', 'source', 'externalReference'] as $key) {
            if (\array_key_exists($key, $data)) {
                $update[$key] = $data[$key] !== null ? (string) $data[$key] : null;
            }
        }

        if (\array_key_exists('amount', $data)) {
            $update['amount'] = $data['amount'] !== null ? (float) $data['amount'] : null;
        }

        if ($flagAdjustment) {
            $update['paymentStatus'] = self::STATUS_ADJUSTMENT_REQUIRED;
        }

        $this->partialDeliveryRepository->update([$update], $context);

        $entity = $this->partialDeliveryRepository->search(new Criteria([$id]), $context)->first();
        if ($entity instanceof PartialDeliveryEntity) {
            $this->eventDispatcher->dispatch(new PartialDeliveryUpdatedEvent($entity, $context));

            if ($flagAdjustment) {
                $this->eventDispatcher->dispatch(new PartialDeliveryCaptureAdjustmentRequiredEvent($entity, $context));
            }
        }

        return ['success' => true, 'partialDeliveryId' => $id, 'reason' => null];
    }

    /**
     * Return all partial deliveries of an order, grouped per order line item.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getShipmentsByOrder(string $orderId, Context $context): array
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');

        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order instanceof OrderEntity || $order->getLineItems() === null) {
            return [];
        }

        $result = [];
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== 'product') {
                continue;
            }

            $delivered = 0;
            $rows = [];
            foreach ($this->findByLineItem($lineItem->getId(), $context) as $shipment) {
                $delivered += $shipment->getQuantity();
                $rows[] = [
                    'id' => $shipment->getId(),
                    'quantity' => $shipment->getQuantity(),
                    'package' => $shipment->getPackage(),
                    'trackingCode' => $shipment->getTrackingCode(),
                    'paymentStatus' => $shipment->getPaymentStatus(),
                    'paymentReference' => $shipment->getPaymentReference(),
                    'source' => $shipment->getSource(),
                    'externalReference' => $shipment->getExternalReference(),
                    'amount' => $shipment->getAmount(),
                    'createdAt' => $shipment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                ];
            }

            $ordered = $lineItem->getQuantity();
            $result[] = [
                'orderLineItemId' => $lineItem->getId(),
                'productNumber' => $lineItem->getPayload()['productNumber'] ?? null,
                'quantityOrdered' => $ordered,
                'quantityDelivered' => $delivered,
                'quantityRemaining' => max(0, $ordered - $delivered),
                'shipments' => $rows,
            ];
        }

        return $result;
    }

    /**
     * Persist a payment status / reference on a single partial-delivery row.
     * Intended to be called by payment plugins after they process a capture.
     */
    public function setPaymentStatus(
        string $partialDeliveryId,
        string $status,
        ?string $reference,
        Context $context
    ): void {
        $this->partialDeliveryRepository->update([[
            'id' => $partialDeliveryId,
            'paymentStatus' => $status,
            'paymentReference' => $reference,
        ]], $context);
    }

    /**
     * Resolve the best matching payment-capture handler for the order and run it.
     *
     * Capture is idempotent: only deliveries that are not yet captured/pending are
     * processed, and only those rows receive the result. Re-running capture after a
     * new shipment therefore captures only the newly added deliveries and never
     * re-charges already-captured ones.
     */
    public function requestCapture(PartialDeliveryCaptureRequest $request, Context $context): PartialDeliveryCaptureResult
    {
        $criteria = new Criteria([$request->orderId]);
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('currency');

        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order instanceof OrderEntity) {
            return PartialDeliveryCaptureResult::failed('Order not found: ' . $request->orderId);
        }

        $eligibleIds = $this->resolveCaptureEligibleIds($request, $context);
        if ($eligibleIds === []) {
            return PartialDeliveryCaptureResult::captured(
                null,
                'Nothing to capture: matching deliveries are already captured or pending.'
            );
        }

        $this->eventDispatcher->dispatch(new PartialDeliveryCaptureRequestedEvent($order, $request, $context));

        $handler = $this->captureRegistry->getHandler($order, $context);
        $result = $handler !== null
            ? $handler->capture($order, $request, $context)
            : PartialDeliveryCaptureResult::unsupported('No capture handler available.');

        $this->applyStatusToIds($eligibleIds, $result->status, $result->reference, $context);

        $this->eventDispatcher->dispatch(
            new PartialDeliveryCaptureCompletedEvent($order, $request, $result, $context)
        );

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function resolveCaptureEligibleIds(PartialDeliveryCaptureRequest $request, Context $context): array
    {
        if ($request->partialDeliveryId !== null) {
            $row = $this->partialDeliveryRepository->search(new Criteria([$request->partialDeliveryId]), $context)->first();
            if (!$row instanceof PartialDeliveryEntity
                || \in_array($row->getPaymentStatus(), self::NON_CAPTURABLE_STATUSES, true)
            ) {
                return [];
            }

            return [$request->partialDeliveryId];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $request->orderId));

        $ids = [];
        foreach ($this->partialDeliveryRepository->search($criteria, $context)->getEntities() as $row) {
            if (!\in_array($row->getPaymentStatus(), self::NON_CAPTURABLE_STATUSES, true)) {
                $ids[] = $row->getId();
            }
        }

        return $ids;
    }

    /**
     * @param array<int, string> $ids
     */
    private function applyStatusToIds(array $ids, string $status, ?string $reference, Context $context): void
    {
        if ($ids === []) {
            return;
        }

        $payload = array_map(static fn (string $id) => [
            'id' => $id,
            'paymentStatus' => $status,
            'paymentReference' => $reference,
        ], $ids);

        $this->partialDeliveryRepository->update($payload, $context);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isFinancialChange(PartialDeliveryEntity $existing, array $data): bool
    {
        if (\array_key_exists('quantity', $data) && (int) $data['quantity'] !== $existing->getQuantity()) {
            return true;
        }

        if (\array_key_exists('amount', $data)) {
            $newAmount = $data['amount'] !== null ? (float) $data['amount'] : null;
            if ($newAmount !== $existing->getAmount()) {
                return true;
            }
        }

        return false;
    }

    private function getCaptureUpdatePolicy(): string
    {
        $value = $this->systemConfigService->getString(self::CONFIG_CAPTURE_UPDATE_POLICY);

        return \in_array($value, [self::POLICY_PROTECT, self::POLICY_FLAG], true) ? $value : self::POLICY_PROTECT;
    }

    /**
     * @param array<string, mixed> $shipment
     */
    private function resolveLineItem(array $shipment, Context $context): ?OrderLineItemEntity
    {
        $lineItemId = $shipment['orderLineItemId'] ?? null;
        if (\is_string($lineItemId) && Uuid::isValid($lineItemId)) {
            $lineItem = $this->orderLineItemRepository->search(new Criteria([$lineItemId]), $context)->first();

            return $lineItem instanceof OrderLineItemEntity ? $lineItem : null;
        }

        $productNumber = isset($shipment['productNumber']) ? (string) $shipment['productNumber'] : null;
        if ($productNumber === null) {
            return null;
        }

        $orderId = $this->resolveOrderId($shipment, $context);
        if ($orderId === null) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $lineItems = $this->orderLineItemRepository->search($criteria, $context)->getEntities();

        foreach ($lineItems as $lineItem) {
            if (($lineItem->getPayload()['productNumber'] ?? null) === $productNumber) {
                return $lineItem;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $shipment
     */
    private function resolveOrderId(array $shipment, Context $context): ?string
    {
        $orderId = $shipment['orderId'] ?? null;
        if (\is_string($orderId) && Uuid::isValid($orderId)) {
            return $orderId;
        }

        $orderNumber = isset($shipment['orderNumber']) ? (string) $shipment['orderNumber'] : null;
        if ($orderNumber === null) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $order = $this->orderRepository->search($criteria, $context)->first();

        return $order instanceof OrderEntity ? $order->getId() : null;
    }

    private function getRemainingQuantity(
        OrderLineItemEntity $lineItem,
        Context $context,
        ?string $excludePartialDeliveryId = null
    ): int {
        $delivered = 0;
        foreach ($this->findByLineItem($lineItem->getId(), $context) as $shipment) {
            if ($excludePartialDeliveryId !== null && $shipment->getId() === $excludePartialDeliveryId) {
                continue;
            }

            $delivered += $shipment->getQuantity();
        }

        return $lineItem->getQuantity() - $delivered;
    }

    /**
     * @return array<int, PartialDeliveryEntity>
     */
    private function findByLineItem(string $orderLineItemId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderLineItemId', $orderLineItemId));

        /** @var array<int, PartialDeliveryEntity> $elements */
        $elements = array_values($this->partialDeliveryRepository->search($criteria, $context)->getElements());

        return $elements;
    }
}
