<?php

declare(strict_types=1);

namespace PartialDelivery\Event;

use PartialDelivery\Core\Content\PartialDelivery\PartialDeliveryEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after an existing partial delivery row has been updated.
 */
class PartialDeliveryUpdatedEvent extends Event
{
    public const NAME = 'partial_delivery.updated';

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
