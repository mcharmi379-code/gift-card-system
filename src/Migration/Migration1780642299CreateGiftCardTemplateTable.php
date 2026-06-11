<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1780642299CreateGiftCardTemplateTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780642299;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ictech_gift_card_template` (
                `id`            BINARY(16)                              NOT NULL,
                `name`          VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `tag`           VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `media_id`      BINARY(16)                                  NULL,
                `active`        TINYINT(1) UNSIGNED                     NOT NULL DEFAULT 1,
                `custom_fields` JSON                                        NULL,
                `created_at`    DATETIME(3)                             NOT NULL,
                `updated_at`    DATETIME(3)                                 NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.ictech_gift_card_template.custom_fields`
                    CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `fk.ictech_gift_card_template.media_id`
                    FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                INDEX `idx.ictech_gift_card_template.active` (`active`),
                INDEX `idx.ictech_gift_card_template.media_id` (`media_id`)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci;
            SQL
        );
    }
}
