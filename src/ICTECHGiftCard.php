<?php

declare(strict_types=1);

namespace ICTECHGiftCard;

use Doctrine\DBAL\Connection;
use ICTECHGiftCard\Service\GiftCardNavigationInstaller;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Uuid\Uuid;

final class ICTECHGiftCard extends Plugin
{
    public const MAIL_TYPE_RECIPIENT = 'ictech_gift_card';
    public const MAIL_TYPE_PURCHASER_CONFIRMATION = 'ictech_gift_card_purchaser_confirmation';
    public const MAIL_TYPE_PURCHASER_SELF = 'ictech_gift_card_purchaser_self';

    public const MAIL_TEMPLATES = [
        self::MAIL_TYPE_RECIPIENT => [
            'name' => 'Gift Card (Recipient)',
            'description' => 'Gift card email sent to recipient with voucher code',
            'subject' => 'Your Gift Card Code: {{ voucher_code }}',
        ],
        self::MAIL_TYPE_PURCHASER_CONFIRMATION => [
            'name' => 'Gift Card Purchaser Confirmation',
            'description' => 'Gift card confirmation email sent to purchaser',
            'subject' => 'Gift Card Purchase Confirmation',
        ],
        self::MAIL_TYPE_PURCHASER_SELF => [
            'name' => 'Gift Card Purchaser Self-Send',
            'description' => 'Gift card email sent to purchaser when self-send is selected',
            'subject' => 'Your Gift Card',
        ],
    ];

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

        $this->createMailTemplateTypes();
        $categoryRepository = $this->container?->get('category.repository');
        $salesChannelRepository = $this->container?->get('sales_channel.repository');
        $languageRepository = $this->container?->get('language.repository');

        if ($categoryRepository instanceof EntityRepository && $salesChannelRepository instanceof EntityRepository && $languageRepository instanceof EntityRepository) {
            $installer = new GiftCardNavigationInstaller($categoryRepository, $salesChannelRepository, $languageRepository);
            $installer->install($installContext->getContext());
        }
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        $this->createMailTemplateTypes();
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        $categoryRepository = $this->container?->get('category.repository');
        $salesChannelRepository = $this->container?->get('sales_channel.repository');
        $languageRepository = $this->container?->get('language.repository');

        if ($categoryRepository instanceof EntityRepository && $salesChannelRepository instanceof EntityRepository && $languageRepository instanceof EntityRepository) {
            $installer = new GiftCardNavigationInstaller($categoryRepository, $salesChannelRepository, $languageRepository);
            $installer->install($activateContext->getContext());
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $container = $this->container;
        if ($container === null) {
            return;
        }

        $connection = $container->get(Connection::class);
        if (! $connection instanceof Connection) {
            return;
        }

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->uninstallCategories($container, $connection, $uninstallContext->getContext());
        $this->removeMailTemplateTypes($connection);
        $this->uninstallProducts($container, $connection, $uninstallContext->getContext());
        $this->dropTables($connection);
    }

    private function uninstallCategories(
        \Symfony\Component\DependencyInjection\ContainerInterface $container,
        Connection $connection,
        \Shopware\Core\Framework\Context $context,
    ): void {
        $this->deleteNavigationCategories($container, $context);
        $this->deleteCategoriesByCustomFields($connection);
    }

    private function deleteNavigationCategories(
        \Symfony\Component\DependencyInjection\ContainerInterface $container,
        \Shopware\Core\Framework\Context $context,
    ): void {
        try {
            $salesChannelRepository = $container->get('sales_channel.repository');
            $categoryRepository = $container->get('category.repository');
            if ($salesChannelRepository instanceof EntityRepository && $categoryRepository instanceof EntityRepository) {
                $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
                $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
                /** @var \Shopware\Core\System\SalesChannel\SalesChannelCollection $salesChannels */
                $salesChannels = $salesChannelRepository->search($criteria, $context)->getEntities();

                $idsToDelete = [];
                foreach ($salesChannels as $salesChannel) {
                    $navigationCategoryId = $salesChannel->getNavigationCategoryId();
                    if ($navigationCategoryId !== '' && Uuid::isValid($navigationCategoryId)) {
                        $idsToDelete[] = [
                            'id' => Uuid::fromStringToHex('ictech-gift-card-navigation-' . $navigationCategoryId),
                        ];
                    }
                }

                if (\count($idsToDelete) > 0) {
                    $categoryRepository->delete($idsToDelete, $context);
                }
            }
        } catch (\Throwable) {
            // Fallback
        }
    }

    private function deleteCategoriesByCustomFields(Connection $connection): void
    {
        try {
            $connection->executeStatement(
                'DELETE FROM category WHERE id IN (SELECT DISTINCT category_id FROM category_translation WHERE custom_fields LIKE :search)',
                ['search' => '%ictech_gift_card_navigation%']
            );
        } catch (\Throwable) {
        }
    }

    private function uninstallProducts(
        \Symfony\Component\DependencyInjection\ContainerInterface $container,
        Connection $connection,
        \Shopware\Core\Framework\Context $context,
    ): void {
        try {
            $tableExists = $connection->fetchOne("SHOW TABLES LIKE 'ictech_gift_card'");
            if (! $tableExists) {
                return;
            }

            $productIds = $connection->fetchFirstColumn(
                'SELECT DISTINCT product_id FROM ictech_gift_card WHERE product_id IS NOT NULL'
            );

            if (\count($productIds) > 0) {
                $productRepository = $container->get('product.repository');
                if ($productRepository instanceof EntityRepository) {
                    $payload = \array_map(static function (mixed $id): array {
                        $idStr = \is_string($id) ? $id : '';
                        return ['id' => \bin2hex($idStr)];
                    }, $productIds);
                    $productRepository->delete($payload, $context);
                }
            }
        } catch (\Throwable $e) {
            error_log('Failed to uninstall gift card products: ' . $e->getMessage());
        }
    }

    private function dropTables(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_audit_log`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_transaction`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_voucher`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_template_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_template`');
    }

    private function createMailTemplateTypes(): void
    {
        $connection = $this->container?->get(Connection::class);
        if (! $connection instanceof Connection) {
            return;
        }

        foreach (self::MAIL_TEMPLATES as $technicalName => $config) {
            $fullConfig = $config;
            $fullConfig['content_html'] = $this->getHtmlTemplateByTechnicalName($technicalName);
            $fullConfig['content_plain'] = $this->getPlainTemplateByTechnicalName($technicalName);

            $this->upsertMailTemplateType($connection, $technicalName, $fullConfig);
        }
    }

    private function getHtmlTemplateByTechnicalName(string $technicalName): string
    {
        if ($technicalName === self::MAIL_TYPE_RECIPIENT) {
            return $this->getDefaultHtmlTemplate();
        }
        if ($technicalName === self::MAIL_TYPE_PURCHASER_CONFIRMATION) {
            return $this->getPurchaserConfirmationHtml();
        }
        return $this->getDefaultSelfHtml();
    }

    private function getPlainTemplateByTechnicalName(string $technicalName): string
    {
        if ($technicalName === self::MAIL_TYPE_RECIPIENT) {
            return $this->getDefaultPlainTemplate();
        }
        if ($technicalName === self::MAIL_TYPE_PURCHASER_CONFIRMATION) {
            return $this->getPurchaserConfirmationPlain();
        }
        return $this->getDefaultSelfPlain();
    }

    /**
     * @param array{name: string, description: string, subject: string, content_html: string, content_plain: string} $config
     */
    private function upsertMailTemplateType(Connection $connection, string $technicalName, array $config): void
    {
        $exists = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => $technicalName]
        );

        if ($exists !== false) {
            return;
        }

        $typeId = Uuid::randomBytes();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.000');

        $connection->insert('mail_template_type', [
            'id' => $typeId,
            'technical_name' => $technicalName,
            'available_entities' => json_encode(['voucher' => 'ictech_gift_card_voucher'], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        $enLangId = $this->getLanguageId($connection, 'en-GB')
            ?? $this->getDefaultLanguageId($connection);

        if ($enLangId !== null) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id' => $enLangId,
                'name' => $config['name'],
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

        if ($enLangId !== null) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $enLangId,
                'sender_name' => '{{ shopName }}',
                'subject' => $config['subject'],
                'description' => $config['description'],
                'content_html' => $config['content_html'],
                'content_plain' => $config['content_plain'],
                'created_at' => $now,
            ]);
        }
    }

    private function removeMailTemplateTypes(Connection $connection): void
    {
        $technicalNames = \array_keys(self::MAIL_TEMPLATES);

        foreach ($technicalNames as $technicalName) {
            $typeId = $connection->fetchOne(
                'SELECT id FROM mail_template_type WHERE technical_name = :name',
                ['name' => $technicalName]
            );

            if ($typeId === false) {
                continue;
            }

            $connection->executeStatement(
                'DELETE FROM mail_template WHERE mail_template_type_id = :id',
                ['id' => $typeId]
            );

            $connection->executeStatement(
                'DELETE FROM mail_template_type WHERE id = :id',
                ['id' => $typeId]
            );
        }
    }

    private function getLanguageId(Connection $connection, string $locale): ?string
    {
        $result = $connection->fetchOne(
            'SELECT l.id FROM language l
             INNER JOIN locale lo ON lo.id = l.locale_id
             WHERE lo.code = :code LIMIT 1',
            ['code' => $locale]
        );

        return \is_string($result) ? $result : null;
    }

    private function getDefaultLanguageId(Connection $connection): ?string
    {
        $result = $connection->fetchOne(
            'SELECT id FROM language WHERE id = UNHEX(:id)',
            ['id' => Defaults::LANGUAGE_SYSTEM]
        );

        return \is_string($result) ? $result : null;
    }

    private function getPurchaserConfirmationHtml(): string
    {
        return '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
                <h2>🎁 Gift Card Purchase Confirmation</h2>
                <p>Hi {{ purchaser_name }},</p>
                <p>Thank you for purchasing a gift card of <strong>€{{ amount }}</strong> for {{ recipient_name }}.</p>
                <p>It is scheduled to be sent to {{ recipient_email }} on {{ send_date }}.</p>
                <p>Gift Card Code: {{ voucher_code }}</p>
                <p>Valid until {{ validity_date }}</p>
                <p><a href="{{ shop_url }}" style="background:#57D9A3;color:#fff;padding:12px 24px;border-radius:4px;text-decoration:none;display:inline-block;">Visit our Shop</a></p>
            </div>
        ';
    }

    private function getPurchaserConfirmationPlain(): string
    {
        return "Hi {{ purchaser_name }},\n\nThank you for purchasing a gift card of €{{ amount }} for {{ recipient_name }}.\n\nIt is scheduled to be sent to {{ recipient_email }} on {{ send_date }}.\n\nGift Card Code: {{ voucher_code }}\nValid until: {{ validity_date }}\n";
    }

    private function getDefaultSelfHtml(): string
    {
        return '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;">
                <h2>🎁 Your Gift Card</h2>
                <p>Hi {{ purchaser_name }},</p>
                <p>Thank you for purchasing a gift card. Here are your gift card details:</p>
                <div style="background:#f5f5f5;padding:20px;text-align:center;border-radius:8px;margin:24px 0;">
                    <p style="margin:0;font-size:12px;color:#999;">Your voucher code</p>
                    <p style="margin:8px 0;font-size:28px;font-weight:bold;letter-spacing:4px;color:#333;">{{ voucher_code }}</p>
                    <p style="margin:0;font-size:12px;color:#999;">Amount: <strong>€{{ amount }}</strong></p>
                    <p style="margin:0;font-size:12px;color:#999;">Valid until {{ validity_date }}</p>
                </div>
                <p>Redemption details: Enter the voucher code in the shopping cart before checking out to redeem it.</p>
                <p><a href="{{ shop_url }}" style="background:#57D9A3;color:#fff;padding:12px 24px;border-radius:4px;text-decoration:none;display:inline-block;">Shop Now</a></p>
            </div>
        ';
    }

    private function getDefaultSelfPlain(): string
    {
        return "Hi {{ purchaser_name }},\n\nThank you for purchasing a gift card. Here are your details:\n\nYour voucher code: {{ voucher_code }}\nAmount: €{{ amount }}\nValid until: {{ validity_date }}\n\nRedemption details: Enter the code in the shopping cart before checking out.\n";
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
