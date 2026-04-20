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
}
