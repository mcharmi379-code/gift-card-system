<?php

declare(strict_types=1);

namespace ICTECHGiftCard;

use Doctrine\DBAL\Connection;
use ICTECHGiftCard\Service\GiftCardNavigationInstaller;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Uuid\Uuid;

final class ICTECHGiftCard extends Plugin
{
    private const MAIL_TEMPLATE_TYPE_TECHNICAL_NAME = 'ictech_gift_card';

    /**
     * @return array<string, list<string>>
     */
    public function enrichPrivileges(): array
    {
        return [
            'additional_permissions' => [
                'ictech_gift_card:read',
                'ictech_gift_card:create',
                'ictech_gift_card:update',
                'ictech_gift_card:delete',
                'ictech_gift_card_voucher:read',
                'ictech_gift_card_voucher:create',
                'ictech_gift_card_voucher:update',
                'ictech_gift_card_voucher:delete',
                'ictech_gift_card_transaction:read',
                'ictech_gift_card_transaction:create',
                'ictech_gift_card_audit_log:read',
                'ictech_gift_card_audit_log:create',
            ],
        ];
    }

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->createMailTemplateType($installContext);
        $categoryRepository = $this->container?->get('category.repository');
        $salesChannelRepository = $this->container?->get('sales_channel.repository');
        $connection = $this->container?->get(Connection::class);

        if ($categoryRepository instanceof EntityRepository && $salesChannelRepository instanceof EntityRepository && $connection instanceof Connection) {
            $installer = new GiftCardNavigationInstaller($categoryRepository, $salesChannelRepository, $connection);
            $installer->install($installContext->getContext());
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container?->get(Connection::class);
        if (!$connection instanceof Connection) {
            return;
        }

        $this->removeMailTemplateType($connection);

        $connection->executeStatement(
            'DELETE FROM category WHERE id IN (SELECT DISTINCT category_id FROM category_translation WHERE custom_fields LIKE :search)',
            ['search' => '%ictech_gift_card_navigation%']
        );

        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_audit_log`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_transaction`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_voucher`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_template_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_template`');
    }

    private function createMailTemplateType(InstallContext $installContext): void
    {
        $connection = $this->container?->get(Connection::class);
        if (!$connection instanceof Connection) {
            return;
        }

        // Check if already exists
        $exists = $connection->fetchOne(
            "SELECT id FROM mail_template_type WHERE technical_name = :name",
            ['name' => self::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME]
        );

        if ($exists !== false) {
            return;
        }

        $typeId = Uuid::randomBytes();
        $now    = (new \DateTimeImmutable())->format('Y-m-d H:i:s.000');

        $connection->insert('mail_template_type', [
            'id'             => $typeId,
            'technical_name' => self::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME,
            'available_entities' => json_encode(['voucher' => 'ictech_gift_card_voucher'], \JSON_THROW_ON_ERROR),
            'created_at'     => $now,
        ]);

        // English translation
        $enLangId = $this->getLanguageId($connection, 'en-GB')
            ?? $this->getDefaultLanguageId($connection);

        if ($enLangId !== null) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id'           => $enLangId,
                'name'                  => 'Gift Card',
                'created_at'            => $now,
            ]);
        }

        // Create default mail template
        $templateId = Uuid::randomBytes();
        $connection->insert('mail_template', [
            'id'                   => $templateId,
            'mail_template_type_id' => $typeId,
            'system_default'       => 1,
            'created_at'           => $now,
        ]);

        if ($enLangId !== null) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id'      => $enLangId,
                'sender_name'      => '{{ shopName }}',
                'subject'          => 'Your Gift Card Code: {{ voucher_code }}',
                'description'      => 'Gift card email sent to recipient',
                'content_html'     => $this->getDefaultHtmlTemplate(),
                'content_plain'    => $this->getDefaultPlainTemplate(),
                'created_at'       => $now,
            ]);
        }
    }

    private function removeMailTemplateType(Connection $connection): void
    {
        $typeId = $connection->fetchOne(
            "SELECT id FROM mail_template_type WHERE technical_name = :name",
            ['name' => self::MAIL_TEMPLATE_TYPE_TECHNICAL_NAME]
        );

        if ($typeId === false) {
            return;
        }

        $connection->executeStatement(
            "DELETE FROM mail_template WHERE mail_template_type_id = :id",
            ['id' => $typeId]
        );

        $connection->executeStatement(
            "DELETE FROM mail_template_type WHERE id = :id",
            ['id' => $typeId]
        );
    }

    private function getLanguageId(Connection $connection, string $locale): ?string
    {
        $result = $connection->fetchOne(
            "SELECT l.id FROM language l
             INNER JOIN locale lo ON lo.id = l.locale_id
             WHERE lo.code = :code LIMIT 1",
            ['code' => $locale]
        );

        return \is_string($result) ? $result : null;
    }

    private function getDefaultLanguageId(Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            "SELECT id FROM language WHERE id = UNHEX(:id)",
            ['id' => Defaults::LANGUAGE_SYSTEM]
        );

        return \is_string($result) ? $result : null;
    }

    private function getDefaultHtmlTemplate(): string
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

    private function getDefaultPlainTemplate(): string
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
