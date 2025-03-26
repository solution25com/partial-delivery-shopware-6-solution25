<?php declare(strict_types=1);

namespace PartialDelivery\Core\Content\PartialDelivery;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;


class PartialDeliveryCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PartialDeliveryEntity::class;
    }

}