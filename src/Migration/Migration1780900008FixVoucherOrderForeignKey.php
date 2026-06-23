<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1780900008FixVoucherOrderForeignKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900008;
    }

    public function update(Connection $connection): void
    {
        // 1. Drop old foreign key and add new composite foreign key on ictech_gift_card_voucher
        try {
            $connection->executeStatement(
                'ALTER TABLE `ictech_gift_card_voucher` DROP FOREIGN KEY `fk.ictech_gift_card_voucher.order_id`'
            );
        } catch (\Throwable) {
            // Ignore if constraint does not exist
        }

        $connection->executeStatement(
            'ALTER TABLE `ictech_gift_card_voucher`
             ADD CONSTRAINT `fk.ictech_gift_card_voucher.order_id`
             FOREIGN KEY (`order_id`, `order_version_id`)
             REFERENCES `order` (`id`, `version_id`)
             ON DELETE SET NULL
             ON UPDATE CASCADE'
        );

        // 2. Drop old foreign key and add new composite foreign key on ictech_gift_card_transaction
        try {
            $connection->executeStatement(
                'ALTER TABLE `ictech_gift_card_transaction` DROP FOREIGN KEY `fk.ictech_gift_card_transaction.order_id`'
            );
        } catch (\Throwable) {
            // Ignore if constraint does not exist
        }

        $connection->executeStatement(
            'ALTER TABLE `ictech_gift_card_transaction`
             ADD CONSTRAINT `fk.ictech_gift_card_transaction.order_id`
             FOREIGN KEY (`order_id`, `order_version_id`)
             REFERENCES `order` (`id`, `version_id`)
             ON DELETE SET NULL
             ON UPDATE CASCADE'
        );

        // 3. Restore any orphaned order_id fields using order_line_item_id mapping
        $connection->executeStatement(
            'UPDATE `ictech_gift_card_voucher` v
             INNER JOIN `order_line_item` oli ON oli.id = v.order_line_item_id
             SET v.order_id = oli.order_id, v.order_version_id = oli.order_version_id
             WHERE v.order_id IS NULL AND v.order_line_item_id IS NOT NULL'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
