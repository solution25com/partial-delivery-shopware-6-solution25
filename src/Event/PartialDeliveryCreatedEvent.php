<?php

declare(strict_types=1);

namespace PartialDelivery\Event;

use PartialDelivery\Core\Content\PartialDelivery\PartialDeliveryEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a partial delivery row has been stored.
 * Listen to this in an ERP/middleware or payment plugin to react to new deliveries.
 */
class PartialDeliveryCreatedEvent extends Event
{
    public const NAME = 'partial_delivery.created';

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
