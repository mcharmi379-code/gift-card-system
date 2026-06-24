<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTemplate\Aggregate\GiftCardTemplateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<GiftCardTemplateTranslationEntity>
 */
class GiftCardTemplateTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return GiftCardTemplateTranslationEntity::class;
    }
}
