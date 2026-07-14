<?php

declare(strict_types=1);

namespace PartialDelivery\Payment;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * Implement this interface in a payment plugin (e.g. Pay.nl) to make partial
 * deliveries trigger a partial payment capture for that provider.
 *
 * Register the implementation as a service tagged "partial_delivery.payment_capture".
 * Use the tag "priority" attribute to control ordering; the first handler whose
 * supports() returns true wins. The bundled NullPaymentCaptureHandler runs last
 * and guarantees a graceful "unsupported" result when no provider matches.
 */
interface PartialDeliveryPaymentCaptureInterface
{
    /**
     * Return true if this handler can capture payments for the given order
     * (typically by inspecting the order's payment method handler identifier).
     */
    public function supports(OrderEntity $order, Context $context): bool;

    /**
     * Perform (or schedule) the partial capture. Implementations MUST NOT throw
     * for "this provider does not support partial capture" — return
     * PartialDeliveryCaptureResult::unsupported() instead. Throwing should be
     * reserved for genuinely unexpected failures.
     */
    public function capture(
        OrderEntity $order,
        PartialDeliveryCaptureRequest $request,
        Context $context
    ): PartialDeliveryCaptureResult;
}
