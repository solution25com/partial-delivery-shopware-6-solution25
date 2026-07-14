<?php

declare(strict_types=1);

namespace PartialDelivery\Event;

use PartialDelivery\Core\Content\PartialDelivery\PartialDeliveryEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when an already-captured partial delivery is edited under the
 * `flag` capture-update policy. The plugin never moves money automatically here —
 * a listener (ERP / payment adapter) decides whether to refund or capture the delta.
 */
class PartialDeliveryCaptureAdjustmentRequiredEvent extends Event
{
    public const NAME = 'partial_delivery.capture_adjustment_required';

    public function __construct(
        private readonly PartialDeliveryEntity $partialDelivery,
        private readonly Context $context
    ) {
    }

    public function getPartialDelivery(): PartialDeliveryEntity
    {
        return $this->partialDelivery;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
