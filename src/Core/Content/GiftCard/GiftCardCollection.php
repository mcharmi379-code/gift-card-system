<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCard;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<GiftCardEntity>
 */
final class GiftCardCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ictech_gift_card_collection';
    }

    protected function getExpectedClass(): string
    {
        return GiftCardEntity::class;
    }
}
