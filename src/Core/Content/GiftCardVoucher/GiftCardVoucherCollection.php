<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardVoucher;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<GiftCardVoucherEntity>
 */
final class GiftCardVoucherCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ictech_gift_card_voucher_collection';
    }

    protected function getExpectedClass(): string
    {
        return GiftCardVoucherEntity::class;
    }
}
