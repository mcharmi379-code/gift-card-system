<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1780642300AddTemplateToGiftCard extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780642300;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllAssociative(
            "SHOW COLUMNS FROM `ictech_gift_card` LIKE 'template_id'"
        );

        if (empty($columns)) {
            $connection->executeStatement(
                <<<'SQL'
                ALTER TABLE `ictech_gift_card`
                    ADD COLUMN `template_id` BINARY(16) NULL,
                    ADD CONSTRAINT `fk.ictech_gift_card.template_id`
                        FOREIGN KEY (`template_id`)
                        REFERENCES `ictech_gift_card_template` (`id`)
                        ON DELETE SET NULL
                        ON UPDATE CASCADE;
                SQL
            );
        }
    }
}
