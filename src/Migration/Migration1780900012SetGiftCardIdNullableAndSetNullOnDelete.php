<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1780900012SetGiftCardIdNullableAndSetNullOnDelete extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900012;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeStatement(
                'ALTER TABLE `ictech_gift_card_voucher` DROP FOREIGN KEY `fk.ictech_gift_card_voucher.gift_card_id`'
            );
        } catch (\Throwable $e) {
            error_log($e->getMessage());
        }

        $connection->executeStatement(
            'ALTER TABLE `ictech_gift_card_voucher` MODIFY COLUMN `gift_card_id` BINARY(16) NULL'
        );

        $connection->executeStatement(
            'ALTER TABLE `ictech_gift_card_voucher`
             ADD CONSTRAINT `fk.ictech_gift_card_voucher.gift_card_id`
             FOREIGN KEY (`gift_card_id`)
             REFERENCES `ictech_gift_card` (`id`)
             ON DELETE SET NULL
             ON UPDATE CASCADE'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
