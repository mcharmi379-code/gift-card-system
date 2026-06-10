<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<GiftCardTemplateEntity>
 */
class GiftCardTemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return GiftCardTemplateEntity::class;
    }
}
