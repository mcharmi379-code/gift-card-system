<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1780566958CreateGiftCardVoucherTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780566958;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ictech_gift_card_voucher` (
                `id`                    BINARY(16)                              NOT NULL,
                `gift_card_id`          BINARY(16)                              NOT NULL,
                `order_id`              BINARY(16)                                  NULL COMMENT 'Order that purchased this voucher',
                `order_line_item_id`    BINARY(16)                                  NULL,
                `customer_id`           BINARY(16)                                  NULL COMMENT 'Purchasing customer',
                `code`                  VARCHAR(64)  COLLATE utf8mb4_unicode_ci NOT NULL,
                `original_amount`       DECIMAL(20, 2)                          NOT NULL,
                `remaining_balance`     DECIMAL(20, 2)                          NOT NULL,
                `currency_id`           BINARY(16)                              NOT NULL,
                `sender_name`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                `recipient_name`        VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `recipient_email`       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `personal_message`      TEXT         COLLATE utf8mb4_unicode_ci     NULL,
                `scheduled_send_date`   DATE                                    NOT NULL COMMENT 'Date the gift card email should be sent',
                `sent_at`               DATETIME(3)                                 NULL,
                `expires_at`            DATE                                    NOT NULL,
                `status`                ENUM(
                                            'pending',
                                            'sent',
                                            'partially_used',
                                            'used',
                                            'expired',
                                            'revoked'
                                        )                                       NOT NULL DEFAULT 'pending',
                `custom_fields`         JSON                                        NULL,
                `created_at`            DATETIME(3)                             NOT NULL,
                `updated_at`            DATETIME(3)                                 NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.ictech_gift_card_voucher.code` (`code`),
                CONSTRAINT `json.ictech_gift_card_voucher.custom_fields`
                    CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `fk.ictech_gift_card_voucher.gift_card_id`
                    FOREIGN KEY (`gift_card_id`)
                    REFERENCES `ictech_gift_card` (`id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,
                CONSTRAINT `fk.ictech_gift_card_voucher.order_id`
                    FOREIGN KEY (`order_id`)
                    REFERENCES `order` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `fk.ictech_gift_card_voucher.customer_id`
                    FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE,
                CONSTRAINT `fk.ictech_gift_card_voucher.currency_id`
                    FOREIGN KEY (`currency_id`)
                    REFERENCES `currency` (`id`)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE,
                INDEX `idx.ictech_gift_card_voucher.status` (`status`),
                INDEX `idx.ictech_gift_card_voucher.scheduled_send_date` (`scheduled_send_date`, `status`),
                INDEX `idx.ictech_gift_card_voucher.recipient_email` (`recipient_email`),
                INDEX `idx.ictech_gift_card_voucher.gift_card_id` (`gift_card_id`),
                INDEX `idx.ictech_gift_card_voucher.order_id` (`order_id`),
                INDEX `idx.ictech_gift_card_voucher.customer_id` (`customer_id`),
                INDEX `idx.ictech_gift_card_voucher.expires_at` (`expires_at`, `status`)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci;
            SQL
        );
    }
}
