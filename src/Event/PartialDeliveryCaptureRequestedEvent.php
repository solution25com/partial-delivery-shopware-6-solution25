<?php

declare(strict_types=1);

namespace PartialDelivery\Event;

use PartialDelivery\Payment\PartialDeliveryCaptureRequest;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before a capture handler is invoked. Allows listeners to observe or
 * audit capture attempts without modifying the core flow.
 */
class PartialDeliveryCaptureRequestedEvent extends Event
{
    public const NAME = 'partial_delivery.capture_requested';

    public function __construct(
        private readonly OrderEntity $order,
        private readonly PartialDeliveryCaptureRequest $request,
        private readonly Context $context
    ) {
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getRequest(): PartialDeliveryCaptureRequest
    {
        return $this->request;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
