<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780566959CreateGiftCardTransactionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780566959;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ictech_gift_card_transaction` (
                `id`                  BINARY(16)                              NOT NULL,
                `voucher_id`          BINARY(16)                              NOT NULL,
                `order_id`            BINARY(16)                                  NULL COMMENT 'Redemption order',
                `customer_id`         BINARY(16)                                  NULL,
                `amount_used`         DECIMAL(20, 2)                          NOT NULL,
                `balance_before`      DECIMAL(20, 2)                          NOT NULL,
                `balance_after`       DECIMAL(20, 2)                          NOT NULL,
                `created_at`          DATETIME(3)                             NOT NULL,
                `updated_at`          DATETIME(3)                                 NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.ictech_gift_card_transaction.voucher_id`
                    FOREIGN KEY (`voucher_id`)
                    REFERENCES `ictech_gift_card_voucher` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT `fk.ictech_gift_card_transaction.order_id`
                    FOREIGN KEY (`order_id`)
                    REFERENCES `order` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `fk.ictech_gift_card_transaction.customer_id`
                    FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                INDEX `idx.ictech_gift_card_transaction.voucher_id` (`voucher_id`),
                INDEX `idx.ictech_gift_card_transaction.order_id` (`order_id`),
                INDEX `idx.ictech_gift_card_transaction.customer_id` (`customer_id`)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci;
            SQL
        );
    }
}
