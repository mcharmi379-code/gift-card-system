<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

final class Migration1780900006SeedGiftCardMailTemplate extends MigrationStep
{
    private const TECHNICAL_NAME = 'ictech_gift_card';

    public function getCreationTimestamp(): int
    {
        return 1780900006;
    }

    public function update(Connection $connection): void
    {
        // Skip if already exists
        $exists = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => self::TECHNICAL_NAME]
        );

        if ($exists !== false) {
            return;
        }

        $typeId = Uuid::randomBytes();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.000');

        $connection->insert('mail_template_type', [
            'id' => $typeId,
            'technical_name' => self::TECHNICAL_NAME,
            'available_entities' => json_encode(['voucher' => 'ictech_gift_card_voucher'], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        $langId = $this->getEnglishLanguageId($connection);

        if ($langId !== null) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id' => $langId,
                'name' => 'Gift Card',
                'created_at' => $now,
            ]);
        }

        $templateId = Uuid::randomBytes();
        $connection->insert('mail_template', [
            'id' => $templateId,
            'mail_template_type_id' => $typeId,
            'system_default' => 1,
            'created_at' => $now,
        ]);

        if ($langId !== null) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $langId,
                'sender_name' => '{{ shopName }}',
                'subject' => 'Your Gift Card Code: {{ voucher_code }}',
                'description' => 'Gift card email sent to recipient with voucher code',
                'content_html' => $this->getHtmlTemplate(),
                'content_plain' => $this->getPlainTemplate(),
                'created_at' => $now,
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getEnglishLanguageId(Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            'SELECT l.id FROM language l
             INNER JOIN locale lo ON lo.id = l.locale_id
             WHERE lo.code = :code LIMIT 1',
            ['code' => 'en-GB']
        );

        if (\is_string($result)) {
            return $result;
        }

        // Fall back to system default language
        $result = $connection->fetchOne(
            'SELECT id FROM language WHERE id = UNHEX(:id)',
            ['id' => Defaults::LANGUAGE_SYSTEM]
        );

        return \is_string($result) ? $result : null;
    }

    private function getHtmlTemplate(): string
    {
        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
    <h2>🎁 Your Gift Card</h2>
    <p>Hi {{ recipient_name }},</p>
    <p>{{ sender_name }} has sent you a gift card worth <strong>€{{ amount }}</strong>!</p>
    {% if message %}
    <blockquote style="border-left:4px solid #57D9A3;padding-left:16px;color:#555;">
        {{ message }}
    </blockquote>
    {% endif %}
    <div style="text-align:center;margin:20px 0;">
        {{ card_image|raw }}
    </div>
    <div style="background:#f5f5f5;padding:20px;text-align:center;border-radius:8px;margin:24px 0;">
        <p style="margin:0;font-size:12px;color:#999;">Your voucher code</p>
        <p style="margin:8px 0;font-size:28px;font-weight:bold;letter-spacing:4px;color:#333;">{{ voucher_code }}</p>
        <p style="margin:0;font-size:12px;color:#999;">Valid until {{ validity_date }}</p>
    </div>
    <p>
        <a href="{{ shop_url }}" style="background:#57D9A3;color:#fff;padding:12px 24px;border-radius:4px;text-decoration:none;display:inline-block;">
            Shop Now
        </a>
    </p>
    <p style="font-size:12px;color:#999;">
        To redeem, enter the voucher code at checkout. The amount will be deducted from your cart total.
        Any remaining balance can be used in future purchases until {{ validity_date }}.
    </p>
</div>
HTML;
    }

    private function getPlainTemplate(): string
    {
        return <<<TEXT
Your Gift Card

Hi {{ recipient_name }},

{{ sender_name }} has sent you a gift card worth €{{ amount }}!

{% if message %}
Message: {{ message }}
{% endif %}

Your voucher code: {{ voucher_code }}
Valid until: {{ validity_date }}

Shop at: {{ shop_url }}

To redeem, enter the code at checkout.
TEXT;
    }
}
