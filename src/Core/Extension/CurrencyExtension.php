<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Extension;

use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\CurrencyDefinition;

class CurrencyExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('ictechGiftCardVouchers', GiftCardVoucherDefinition::class, 'currency_id'))->addFlags(new RestrictDelete())
        );
    }

    public function getDefinitionClass(): string
    {
        return CurrencyDefinition::class;
    }

    public function getEntityName(): string
    {
        return CurrencyDefinition::ENTITY_NAME;
    }
}
