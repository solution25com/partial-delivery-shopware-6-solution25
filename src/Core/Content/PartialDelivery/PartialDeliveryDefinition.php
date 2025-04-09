<?php declare(strict_types=1);

namespace PartialDelivery\Core\Content\PartialDelivery;

use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class PartialDeliveryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'partial_delivery';

    public function getEntityName(): string{
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PartialDeliveryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PartialDeliveryCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('order_line_item_id', 'orderLineItemId' ))->addFlags(new ApiAware(), new Required()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
            (new StringField('package', 'package'))->addFlags(new Required()),
            (new StringField('tracking_code', 'trackingCode'))->addFlags(new Required()),
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
            new DateTimeField('updated_at', 'updatedAt'),

            (new ManyToOneAssociationField(
                'orderDeliveryPosition',
                'order_line_item_id',
                OrderDeliveryPositionDefinition::class
            ))->addFlags(new Required()),

        ]);
    }

}