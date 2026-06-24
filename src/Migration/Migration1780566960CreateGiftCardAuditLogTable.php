<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780566960CreateGiftCardAuditLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780566960;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ictech_gift_card_audit_log` (
                `id`                  BINARY(16)                              NOT NULL,
                `voucher_id`          BINARY(16)                                  NULL COMMENT 'NULL if voucher was hard-deleted',
                `admin_user_id`       BINARY(16)                                  NULL,
                `action`              ENUM(
                                          'revoke',
                                          'balance_adjust',
                                          'manual_resend',
                                          'status_change'
                                      )                                       NOT NULL,
                `old_value`           VARCHAR(255) COLLATE utf8mb4_unicode_ci     NULL COMMENT 'Serialised previous state',
                `new_value`           VARCHAR(255) COLLATE utf8mb4_unicode_ci     NULL COMMENT 'Serialised new state',
                `reason`              TEXT         COLLATE utf8mb4_unicode_ci     NULL,
                `created_at`          DATETIME(3)                             NOT NULL,
                `updated_at`          DATETIME(3)                                 NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.ictech_gift_card_audit_log.voucher_id`
                    FOREIGN KEY (`voucher_id`)
                    REFERENCES `ictech_gift_card_voucher` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                INDEX `idx.ictech_gift_card_audit_log.voucher_id` (`voucher_id`),
                INDEX `idx.ictech_gift_card_audit_log.action` (`action`),
                INDEX `idx.ictech_gift_card_audit_log.admin_user_id` (`admin_user_id`)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci;
            SQL
        );
    }
}
