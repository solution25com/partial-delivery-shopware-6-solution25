<?php

declare(strict_types=1);

namespace PartialDelivery\Payment;

/**
 * Immutable value object describing a partial-delivery capture request.
 *
 * This is the input a payment provider receives. Providers only need to read it;
 * they never construct it themselves.
 */
class PartialDeliveryCaptureRequest
{
    /**
     * @param array<string, mixed> $payload Free-form extra data passed by the caller (provider-specific hints).
     */
    public function __construct(
        public readonly string $orderId,
        public readonly ?string $partialDeliveryId = null,
        public readonly ?float $amount = null,
        public readonly ?string $currencyCode = null,
        public readonly ?string $externalReference = null,
        public readonly array $payload = []
    ) {
    }
}
