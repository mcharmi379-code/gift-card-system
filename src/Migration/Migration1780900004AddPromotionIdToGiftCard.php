<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1780900004AddPromotionIdToGiftCard extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900004;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ictech_gift_card' AND COLUMN_NAME = 'promotion_id'"
        );

        if ($columns === []) {
            $connection->executeStatement(
                'ALTER TABLE `ictech_gift_card`
                 ADD COLUMN `promotion_id` BINARY(16) NULL AFTER `product_version_id`'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
