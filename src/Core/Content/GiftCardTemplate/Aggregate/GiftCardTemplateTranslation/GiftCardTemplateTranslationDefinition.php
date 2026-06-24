<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTemplate\Aggregate\GiftCardTemplateTranslation;

use ICTECHGiftCard\Core\Content\GiftCardTemplate\GiftCardTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class GiftCardTemplateTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'ictech_gift_card_template_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return GiftCardTemplateTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return GiftCardTemplateTranslationCollection::class;
    }

    public function getParentDefinitionClass(): string
    {
        return GiftCardTemplateDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new StringField('tag', 'tag'))->addFlags(new ApiAware()),
        ]);
    }
}
