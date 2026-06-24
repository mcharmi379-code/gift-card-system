<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Pool vouchers (status=waiting_valid_order) have no recipient/schedule yet.
 * Make these columns nullable so we don't need placeholder values.
 */
final class Migration1780900005MakeVoucherPoolFieldsNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900005;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `ictech_gift_card_voucher`
                MODIFY COLUMN `sender_name`          VARCHAR(255) NULL DEFAULT NULL,
                MODIFY COLUMN `recipient_name`       VARCHAR(255) NULL DEFAULT NULL,
                MODIFY COLUMN `recipient_email`      VARCHAR(255) NULL DEFAULT NULL,
                MODIFY COLUMN `scheduled_send_date`  DATE         NULL DEFAULT NULL,
                MODIFY COLUMN `expires_at`           DATE         NULL DEFAULT NULL
        ');

        // Clear the placeholder values from any existing pool vouchers
        $connection->executeStatement("
            UPDATE `ictech_gift_card_voucher`
            SET
                `sender_name`         = NULL,
                `recipient_name`      = NULL,
                `recipient_email`     = NULL,
                `scheduled_send_date` = NULL
            WHERE `status` = 'waiting_valid_order'
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
