<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Extension;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardDefinition;
use ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class MediaExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToManyAssociationField('ictechGiftCards', GiftCardDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete())
        );
        $collection->add(
            (new OneToManyAssociationField('ictechGiftCardTemplates', GiftCardTemplateDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete())
        );
    }

    public function getDefinitionClass(): string
    {
        return MediaDefinition::class;
    }

    public function getEntityName(): string
    {
        return MediaDefinition::ENTITY_NAME;
    }
}
