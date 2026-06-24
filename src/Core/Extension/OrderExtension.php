<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Extension;

use ICTECHGiftCard\Core\Content\GiftCardTransaction\GiftCardTransactionDefinition;
use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

final class OrderExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('ictechGiftCardVouchers', GiftCardVoucherDefinition::class, 'order_id'))->addFlags(new SetNullOnDelete())
        );
        $collection->add(
            (new OneToManyAssociationField('ictechGiftCardTransactions', GiftCardTransactionDefinition::class, 'order_id'))->addFlags(new SetNullOnDelete())
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }

    public function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }
}
