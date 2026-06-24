<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTransaction;

use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class GiftCardTransactionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'ictech_gift_card_transaction';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GiftCardTransactionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GiftCardTransactionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('voucher_id', 'voucherId', GiftCardVoucherDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ManyToOneAssociationField('voucher', 'voucher_id', GiftCardVoucherDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FloatField('amount_used', 'amountUsed'))->addFlags(new Required(), new ApiAware()),
            (new FloatField('balance_before', 'balanceBefore'))->addFlags(new Required(), new ApiAware()),
            (new FloatField('balance_after', 'balanceAfter'))->addFlags(new Required(), new ApiAware()),

            (new DateTimeField('created_at', 'createdAt'))->addFlags(new ApiAware()),
            (new DateTimeField('updated_at', 'updatedAt'))->addFlags(new ApiAware()),
        ]);
    }
}
