<?php

declare(strict_types=1);

namespace PartialDelivery\Payment\Example;

use PartialDelivery\Payment\PartialDeliveryCaptureRequest;
use PartialDelivery\Payment\PartialDeliveryCaptureResult;
use PartialDelivery\Payment\PartialDeliveryPaymentCaptureInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * EXAMPLE / SKELETON — a starting point for a payment-provider capture handler.
 *
 * This class is intentionally **not registered** in services.xml, so it never runs
 * by default and capture stays a safe no-op ("unsupported"). Copy it into your
 * payment plugin (or uncomment the registration shown below), then implement the
 * body of capture() against your provider's API.
 *
 * To enable it, add to your plugin's services.xml:
 *
 *   <service id="PartialDelivery\Payment\Example\ExamplePaymentCaptureHandler">
 *       <tag name="partial_delivery.payment_capture" priority="100"/>
 *   </service>
 *
 * The first registered handler whose supports() returns true wins; the bundled
 * NullPaymentCaptureHandler (priority -1000) is the fallback.
 */
class ExamplePaymentCaptureHandler implements PartialDeliveryPaymentCaptureInterface
{
    /**
     * Adjust this to match the handler identifier exposed by your payment method
     * (check `payment_method.handler_identifier`).
     */
    private const PAYMENT_HANDLER_NEEDLE = 'YourProviderPayment';

    public function supports(OrderEntity $order, Context $context): bool
    {
        $handlerIdentifier = $order->getTransactions()?->last()
            ?->getPaymentMethod()
            ?->getHandlerIdentifier();

        return $handlerIdentifier !== null
            && stripos($handlerIdentifier, self::PAYMENT_HANDLER_NEEDLE) !== false;
    }

    public function capture(
        OrderEntity $order,
        PartialDeliveryCaptureRequest $request,
        Context $context
    ): PartialDeliveryCaptureResult {
        // TODO: implement against your provider's API. Outline:
        //
        // 1. Resolve the provider transaction id for this order
        //    (e.g. from the order transaction).
        // 2. Call the provider's capture endpoint with $request->amount (or the full
        //    remaining amount when amount is null) and $request->currencyCode.
        // 3. Map the provider response:
        //      - success (synchronous)  -> PartialDeliveryCaptureResult::captured($reference)
        //      - accepted (async)       -> PartialDeliveryCaptureResult::requested($reference)
        //      - provider rejected      -> PartialDeliveryCaptureResult::failed($message, $reference)
        //
        // Do NOT throw for "this provider cannot partially capture this order" — return
        // PartialDeliveryCaptureResult::unsupported('...') so the flow degrades gracefully.

        return PartialDeliveryCaptureResult::failed(
            'Partial capture is not implemented yet. Implement '
            . self::class . '::capture() against your provider API.'
        );
    }
}
