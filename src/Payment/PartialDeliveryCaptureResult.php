<?php

declare(strict_types=1);

namespace PartialDelivery\Payment;

/**
 * Result returned by a payment-capture handler and stored on the partial_delivery row
 * (payment_status / payment_reference).
 */
class PartialDeliveryCaptureResult
{
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNSUPPORTED = 'unsupported';

    public function __construct(
        public readonly string $status,
        public readonly ?string $reference = null,
        public readonly ?string $message = null
    ) {
    }

    public static function captured(?string $reference = null, ?string $message = null): self
    {
        return new self(self::STATUS_CAPTURED, $reference, $message);
    }

    public static function requested(?string $reference = null, ?string $message = null): self
    {
        return new self(self::STATUS_REQUESTED, $reference, $message);
    }

    public static function failed(?string $message = null, ?string $reference = null): self
    {
        return new self(self::STATUS_FAILED, $reference, $message);
    }

    public static function unsupported(?string $message = null): self
    {
        return new self(self::STATUS_UNSUPPORTED, null, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_CAPTURED || $this->status === self::STATUS_REQUESTED;
    }
}
