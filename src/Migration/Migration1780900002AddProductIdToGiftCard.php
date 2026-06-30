<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780900002AddProductIdToGiftCard extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900002;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative(
            "SHOW COLUMNS FROM `ictech_gift_card` LIKE 'product_id'"
        );

        if ($columns !== []) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `ictech_gift_card`
                ADD COLUMN `product_id` BINARY(16) NULL DEFAULT NULL AFTER `active`,
                ADD CONSTRAINT `fk.ictech_gift_card.product_id`
                    FOREIGN KEY (`product_id`)
                    REFERENCES `product` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
