<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780642301AddOrderVersionId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780642301;
    }

    public function update(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative(
            "SHOW COLUMNS FROM `ictech_gift_card_voucher` LIKE 'order_version_id'"
        );
        if ($rows === []) {
            $connection->executeStatement(
                'ALTER TABLE `ictech_gift_card_voucher`
                    ADD COLUMN `order_version_id` BINARY(16) NULL AFTER `order_id`'
            );
        }

        $rows = $connection->fetchAllAssociative(
            "SHOW COLUMNS FROM `ictech_gift_card_transaction` LIKE 'order_version_id'"
        );
        if ($rows === []) {
            $connection->executeStatement(
                'ALTER TABLE `ictech_gift_card_transaction`
                    ADD COLUMN `order_version_id` BINARY(16) NULL AFTER `order_id`'
            );
        }
    }
}
