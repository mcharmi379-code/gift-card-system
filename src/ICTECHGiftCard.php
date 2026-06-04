<?php

declare(strict_types=1);

namespace ICTECHGiftCard;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

final class ICTECHGiftCard extends Plugin
{
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
