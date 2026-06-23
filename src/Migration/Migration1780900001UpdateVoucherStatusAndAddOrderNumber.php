<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780900001UpdateVoucherStatusAndAddOrderNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            "
                ALTER TABLE `ictech_gift_card_voucher`
                    MODIFY COLUMN `status` ENUM('waiting_valid_order','unused','partially_used','used','canceled')
                        NOT NULL DEFAULT 'waiting_valid_order'
            "
        );

        $columns = $connection->fetchAllAssociative(
            "SHOW COLUMNS FROM `ictech_gift_card_voucher` LIKE 'used_in_order_number'"
        );

        if ($columns === []) {
            $connection->executeStatement("
                ALTER TABLE `ictech_gift_card_voucher`
                    ADD COLUMN `used_in_order_number` VARCHAR(64) NULL DEFAULT NULL
                    AFTER `status`
            ");
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
