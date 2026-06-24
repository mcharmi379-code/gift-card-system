<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardAuditLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<GiftCardAuditLogEntity>
 */
final class GiftCardAuditLogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ictech_gift_card_audit_log_collection';
    }

    protected function getExpectedClass(): string
    {
        return GiftCardAuditLogEntity::class;
    }
}
