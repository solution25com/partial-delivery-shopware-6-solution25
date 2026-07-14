<?php

declare(strict_types=1);

namespace PartialDelivery\Service;

use PartialDelivery\Payment\PartialDeliveryPaymentCaptureInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * Collects all tagged payment-capture handlers (ordered by tag priority) and
 * returns the first one that supports a given order.
 */
class PartialDeliveryCaptureRegistry
{
    /**
     * @param iterable<PartialDeliveryPaymentCaptureInterface> $handlers
     */
    public function __construct(private readonly iterable $handlers)
    {
    }

    public function getHandler(OrderEntity $order, Context $context): ?PartialDeliveryPaymentCaptureInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($order, $context)) {
                return $handler;
            }
        }

        return null;
    }
}
