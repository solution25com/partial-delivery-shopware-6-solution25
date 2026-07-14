<?php

declare(strict_types=1);

namespace PartialDelivery\Payment;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * Default fallback handler. It supports every order but never moves money, so a
 * capture request always resolves gracefully to "unsupported" when no real
 * provider handler is registered. Registered with the lowest priority.
 */
class NullPaymentCaptureHandler implements PartialDeliveryPaymentCaptureInterface
{
    public function supports(OrderEntity $order, Context $context): bool
    {
        return true;
    }

    public function capture(
        OrderEntity $order,
        PartialDeliveryCaptureRequest $request,
        Context $context
    ): PartialDeliveryCaptureResult {
        return PartialDeliveryCaptureResult::unsupported(
            'No payment capture handler is registered for this order\'s payment method. '
            . 'The partial delivery was stored, but no capture was performed.'
        );
    }
}
