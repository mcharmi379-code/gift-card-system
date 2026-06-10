<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTransaction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<GiftCardTransactionEntity>
 */
class GiftCardTransactionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ictech_gift_card_transaction_collection';
    }

    protected function getExpectedClass(): string
    {
        return GiftCardTransactionEntity::class;
    }
}
