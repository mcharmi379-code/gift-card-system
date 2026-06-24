<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1780900007AddDeliveryMethodToVoucher extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900007;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative(
            "SHOW COLUMNS FROM `ictech_gift_card_voucher` LIKE 'delivery_method'"
        );

        if ($columns === []) {
            $connection->executeStatement("
                ALTER TABLE `ictech_gift_card_voucher`
                    ADD COLUMN `delivery_method` VARCHAR(10) NULL DEFAULT NULL COMMENT 'email or print'
            ");
        }

        $templateColumns = $connection->fetchAllAssociative(
            "SHOW COLUMNS FROM `ictech_gift_card_voucher` LIKE 'template_id'"
        );

        if ($templateColumns === []) {
            $connection->executeStatement("
                ALTER TABLE `ictech_gift_card_voucher`
                    ADD COLUMN `template_id` BINARY(16) NULL DEFAULT NULL
            ");
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
