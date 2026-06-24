<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Extension;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class SalesChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('ictechGiftCards', GiftCardDefinition::class, 'sales_channel_id'))->addFlags(new SetNullOnDelete())
        );
    }

    public function getDefinitionClass(): string
    {
        return SalesChannelDefinition::class;
    }

    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }
}
