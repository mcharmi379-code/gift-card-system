<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1780900013MakeTemplateNameAndTagNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900013;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<'SQL'
            ALTER TABLE `ictech_gift_card_template`
            MODIFY COLUMN `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            MODIFY COLUMN `tag` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT '';
            SQL
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
