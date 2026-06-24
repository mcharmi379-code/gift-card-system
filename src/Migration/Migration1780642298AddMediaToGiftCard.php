<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780642298AddMediaToGiftCard extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780642298;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            ALTER TABLE `ictech_gift_card`
                ADD COLUMN `media_id` BINARY(16) NULL AFTER `sales_channel_id`,
                ADD CONSTRAINT `fk.ictech_gift_card.media_id`
                    FOREIGN KEY (`media_id`)
                    REFERENCES `media` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                ADD INDEX `idx.ictech_gift_card.media_id` (`media_id`);
            SQL
        );
    }
}
