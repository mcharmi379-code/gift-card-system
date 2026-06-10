<?php

declare(strict_types=1);

namespace ICTECHGiftCard;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

final class ICTECHGiftCard extends Plugin
{
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

        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_audit_log`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_transaction`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card_voucher`');
        $connection->executeStatement('DROP TABLE IF EXISTS `ictech_gift_card`');
    }
}
