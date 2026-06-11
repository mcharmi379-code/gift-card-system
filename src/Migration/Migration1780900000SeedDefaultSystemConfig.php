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
            'ICTECHGiftCard.config.storefrontText'            => '<p>Delight your loved ones or a friend for their Birthday, Valentine\'s Day, wedding, Christmas... Send a personalised gift card by email to the address of your choice. The amount will then be available as a voucher valid across our entire site.</p>',
            'ICTECHGiftCard.config.storefrontCardWidth'       => 528,
            'ICTECHGiftCard.config.storefrontCardHeight'      => 318,
            'ICTECHGiftCard.config.storefrontCardLargeWidth'  => 800,
            'ICTECHGiftCard.config.storefrontCardLargeHeight' => 518,
            'ICTECHGiftCard.config.storefrontTemplateLabel'   => 'Template',
            'ICTECHGiftCard.config.storefrontPictureLabel'    => 'Picture',
            'ICTECHGiftCard.config.storefrontSenderNameLabel'    => 'Sender name',
            'ICTECHGiftCard.config.storefrontRecipientNameLabel' => 'Recipient name',
            'ICTECHGiftCard.config.storefrontMailRecipientLabel' => 'Email recipient',
            'ICTECHGiftCard.config.storefrontMessageLabel'    => 'Message',
            'ICTECHGiftCard.config.storefrontDateSendLabel'   => 'Date send',
            'ICTECHGiftCard.config.emailSubjectPurchaser'     => 'Your gift card',
            'ICTECHGiftCard.config.emailSubjectRecipient'     => 'Gift card offer from %s',
            'ICTECHGiftCard.config.emailCardWidth'            => 300,
            'ICTECHGiftCard.config.emailCardHeight'           => 194,
            'ICTECHGiftCard.config.pdfPrefix'                 => 'GIFTCARD-',
            'ICTECHGiftCard.config.pdfContent'                => '<table cellpadding="10" style="width:100%;text-align:center;color:#333;background:#ffffff;font-size:14px;"><tbody><tr><td style="width:25%;">&nbsp;</td><td style="width:50%;font-size:30px;border:1px solid #333;"><strong>Gift Card</strong></td><td style="width:25%;">&nbsp;</td></tr><tr><td colspan="3"><p>Hi {{card_lastname}},</p><p>You have received a <strong>{{card_price}}</strong> gift card from {{card_from}}!</p><p style="font-size:18px;margin:0;"><em>Good shopping on {{shop_name}}!</em></p></td></tr><tr><td colspan="3">{{card_image}}</td></tr><tr><td style="width:25%;">&nbsp;</td><td style="width:50%;font-size:16px;background-color:#333;color:#fff;">Your code:<br><strong>{{card_code}}</strong></td><td style="width:25%;">&nbsp;</td></tr><tr><td colspan="3"><p><strong>Message from {{card_from}}</strong></p><div>{{card_message}}</div></td></tr><tr><td colspan="3" style="font-size:1px;"></td></tr><tr><td style="width:33%;font-size:1px;">&nbsp;</td><td style="width:34%;font-size:1px;border-top:1px solid #777;">&nbsp;</td><td style="width:33%;font-size:1px;">&nbsp;</td></tr><tr><td colspan="3"><p style="font-size:16px;"><strong>To take advantage of the gift card</strong></p><p>Copy/paste your code <strong>{{card_code}}</strong> into the shopping cart before checking out.</p></td></tr></tbody></table>',
            'ICTECHGiftCard.config.pdfCardWidth'              => 300,
            'ICTECHGiftCard.config.pdfCardHeight'             => 192,
            'ICTECHGiftCard.config.pdfImageSourceMode'        => 'http',
        ];
    }
}
