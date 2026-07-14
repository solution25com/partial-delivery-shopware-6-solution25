<?php

namespace PartialDelivery\Core\Content\PartialDelivery;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PartialDeliveryEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderLineItemId;
    protected int $quantity;
    protected string $package;
    protected string $trackingCode;
    protected ?string $orderId = null;
    protected ?string $externalReference = null;
    protected ?string $source = null;
    protected ?string $paymentStatus = null;
    protected ?string $paymentReference = null;
    protected ?float $amount = null;

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function setPackage(string $package): void
    {
        $this->package = $package;
    }

    public function getTrackingCode(): string
    {
        return $this->trackingCode;
    }

    public function setTrackingCode(string $trackingCode): void
    {
        $this->trackingCode = $trackingCode;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): void
    {
        $this->externalReference = $externalReference;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): void
    {
        $this->source = $source;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(?string $paymentStatus): void
    {
        $this->paymentStatus = $paymentStatus;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function setPaymentReference(?string $paymentReference): void
    {
        $this->paymentReference = $paymentReference;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): void
    {
        $this->amount = $amount;
    }
}
