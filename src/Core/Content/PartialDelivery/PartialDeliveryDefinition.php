<?php

declare(strict_types=1);

namespace PartialDelivery\Core\Content\PartialDelivery;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

class PartialDeliveryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'partial_delivery';

    public function getEntityName(): string
    {
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
            (new StringField('order_line_item_id', 'orderLineItemId'))->addFlags(new ApiAware(), new Required()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
            (new StringField('package', 'package'))->addFlags(new AllowEmptyString()),
            (new StringField('tracking_code', 'trackingCode'))->addFlags(new AllowEmptyString()),
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
            new DateTimeField('updated_at', 'updatedAt'),

            // Generic integration fields (all optional, populated by external systems / payment providers).
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware()),
            (new StringField('external_reference', 'externalReference'))->addFlags(new ApiAware()),
            (new StringField('source', 'source'))->addFlags(new ApiAware()),
            (new StringField('payment_status', 'paymentStatus'))->addFlags(new ApiAware()),
            (new StringField('payment_reference', 'paymentReference'))->addFlags(new ApiAware()),
            (new FloatField('amount', 'amount'))->addFlags(new ApiAware()),
        ]);
    }
}
