<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780900003AddProductVersionIdToGiftCard extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900003;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative(
            "SHOW COLUMNS FROM `ictech_gift_card` LIKE 'product_version_id'"
        );

        if ($columns !== []) {
            return;
        }

        $connection->executeStatement("
            ALTER TABLE `ictech_gift_card`
                ADD COLUMN `product_version_id` BINARY(16) NULL DEFAULT NULL
                AFTER `product_id`
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
