<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1780900014AddExpiredToVoucherStatus extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900014;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            "ALTER TABLE `ictech_gift_card_voucher`
             MODIFY COLUMN `status` ENUM('waiting_valid_order', 'unused', 'partially_used', 'used', 'canceled', 'expired')
             NOT NULL DEFAULT 'waiting_valid_order'"
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
