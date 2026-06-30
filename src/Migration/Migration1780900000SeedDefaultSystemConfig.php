<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class Migration1780900000SeedDefaultSystemConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780900000;
    }

    public function update(Connection $connection): void
    {
        $defaults = $this->getDefaults();

        foreach ($defaults as $key => $value) {
            $exists = $connection->fetchOne(
                'SELECT COUNT(*) FROM system_config WHERE configuration_key = :key AND sales_channel_id IS NULL',
                ['key' => $key]
            );

            if ($exists !== false && $exists !== '0' && $exists !== 0) {
                continue;
            }

            $connection->executeStatement(
                'INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at)
                 VALUES (:id, :key, :value, NULL, NOW(3))',
                [
                    'id'    => \hex2bin(\bin2hex(\random_bytes(16))),
                    'key'   => $key,
                    'value' => \json_encode(['_value' => $value], \JSON_THROW_ON_ERROR),
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return array<string, string|int>
     */
    private function getDefaults(): array
    {
        return [
            'ICTECHGiftCard.config.storefrontCardWidth'       => 528,
            'ICTECHGiftCard.config.storefrontCardHeight'      => 318,
            'ICTECHGiftCard.config.storefrontTemplateLabel'   => 'Template',
            'ICTECHGiftCard.config.storefrontPictureLabel'    => 'Picture',
            'ICTECHGiftCard.config.storefrontSenderNameLabel'    => 'Sender name',
            'ICTECHGiftCard.config.storefrontRecipientNameLabel' => 'Recipient name',
            'ICTECHGiftCard.config.storefrontMailRecipientLabel' => 'Email recipient',
            'ICTECHGiftCard.config.storefrontMessageLabel'    => 'Message',
            'ICTECHGiftCard.config.storefrontDateSendLabel'   => 'Date send',
            'ICTECHGiftCard.config.emailCardWidth'            => 300,
            'ICTECHGiftCard.config.emailCardHeight'           => 194,
            'ICTECHGiftCard.config.pdfPrefix'                 => 'GIFTCARD-',
            'ICTECHGiftCard.config.pdfCardWidth'              => 300,
            'ICTECHGiftCard.config.pdfCardHeight'             => 192,
        ];
    }
}
