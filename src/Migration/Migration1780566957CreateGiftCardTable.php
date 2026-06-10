<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1780566957CreateGiftCardTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780566957;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ictech_gift_card` (
                `id`                  BINARY(16)                              NOT NULL,
                `name`                VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `amount`              DECIMAL(20, 2)                          NOT NULL,
                `code_prefix`         VARCHAR(20)  COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `validity_days`       INT UNSIGNED                            NOT NULL DEFAULT 365,
                `quantity`            INT UNSIGNED                                NULL COMMENT 'NULL = unlimited',
                `quantity_issued`     INT UNSIGNED                            NOT NULL DEFAULT 0,
                `active`              TINYINT(1) UNSIGNED                     NOT NULL DEFAULT 1,
                `sales_channel_id`    BINARY(16)                                  NULL,
                `custom_fields`       JSON                                        NULL,
                `created_at`          DATETIME(3)                             NOT NULL,
                `updated_at`          DATETIME(3)                                 NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.ictech_gift_card.custom_fields`
                    CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `fk.ictech_gift_card.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                INDEX `idx.ictech_gift_card.active` (`active`),
                INDEX `idx.ictech_gift_card.sales_channel_id` (`sales_channel_id`)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci;
            SQL
        );
    }
}
