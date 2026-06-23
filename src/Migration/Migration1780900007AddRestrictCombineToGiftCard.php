<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1780900007AddRestrictCombineToGiftCard extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900007;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ictech_gift_card' AND COLUMN_NAME = 'restrict_combine'"
        );

        if ($columns === []) {
            $connection->executeStatement(
                'ALTER TABLE `ictech_gift_card`
                 ADD COLUMN `restrict_combine` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
