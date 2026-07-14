<?php

declare(strict_types=1);

namespace PartialDelivery\Event;

use PartialDelivery\Payment\PartialDeliveryCaptureRequest;
use PartialDelivery\Payment\PartialDeliveryCaptureResult;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a capture handler has run, regardless of outcome.
 * Inspect getResult()->getStatus() to distinguish captured / requested / failed / unsupported.
 */
class PartialDeliveryCaptureCompletedEvent extends Event
{
    public const NAME = 'partial_delivery.capture_completed';

    public function __construct(
        private readonly OrderEntity $order,
        private readonly PartialDeliveryCaptureRequest $request,
        private readonly PartialDeliveryCaptureResult $result,
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

    public function getResult(): PartialDeliveryCaptureResult
    {
        return $this->result;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
