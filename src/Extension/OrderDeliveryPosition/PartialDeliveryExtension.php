<?php declare(strict_types=1);

namespace PartialDelivery\Extension\OrderDeliveryPosition;

use PartialDelivery\Core\Content\PartialDelivery\PartialDeliveryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;


class PartialDeliveryExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('partialDeliveries', PartialDeliveryDefinition::class, 'order_line_item_id'))->addFlags(new ApiAware())
        );
    }

    /**
     * @inheritDoc
     */
    public function getDefinitionClass(): string
    {
        return OrderLineItemDefinition::class;
    }
}