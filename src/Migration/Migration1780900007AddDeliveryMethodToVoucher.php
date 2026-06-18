<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1780900007AddDeliveryMethodToVoucher extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900007;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement("
            ALTER TABLE `ictech_gift_card_voucher`
                ADD COLUMN IF NOT EXISTS `delivery_method` VARCHAR(10) NULL DEFAULT NULL COMMENT 'email or print',
                ADD COLUMN IF NOT EXISTS `template_id`     BINARY(16)  NULL DEFAULT NULL
        ");
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
