<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780900012AddTranslationsToGiftCardTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900012;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ictech_gift_card_template_translation` (
                `ictech_gift_card_template_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `tag` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`ictech_gift_card_template_id`, `language_id`),
                CONSTRAINT `fk.ictech_gift_card_template_translation.template_id`
                    FOREIGN KEY (`ictech_gift_card_template_id`)
                    REFERENCES `ictech_gift_card_template` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `fk.ictech_gift_card_template_translation.language_id`
                    FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL
        );

        // Migrate existing template names and tags to the translations table for all languages
        $connection->executeStatement(
            <<<'SQL'
            INSERT IGNORE INTO `ictech_gift_card_template_translation` (`ictech_gift_card_template_id`, `language_id`, `name`, `tag`, `created_at`)
            SELECT t.`id`, l.`id`, t.`name`, t.`tag`, t.`created_at`
            FROM `ictech_gift_card_template` t
            CROSS JOIN `language` l;
            SQL
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
