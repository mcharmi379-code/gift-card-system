<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardAuditLog;

use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use ICTECHGiftCard\Core\Enum\AuditLogAction;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class GiftCardAuditLogEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $voucherId = null;

    protected ?GiftCardVoucherEntity $voucher = null;

    protected ?string $adminUserId = null;

    protected ?\Shopware\Core\System\User\UserEntity $adminUser = null;

    protected ?AuditLogAction $action = null;

    protected ?string $oldValue = null;

    protected ?string $newValue = null;

    protected ?string $reason = null;

    public function getVoucherId(): ?string
    {
        return $this->voucherId;
    }

    public function setVoucherId(?string $voucherId): void
    {
        $this->voucherId = $voucherId;
    }

    public function getVoucher(): ?GiftCardVoucherEntity
    {
        return $this->voucher;
    }

    public function setVoucher(?GiftCardVoucherEntity $voucher): void
    {
        $this->voucher = $voucher;
    }

    public function getAdminUserId(): ?string
    {
        return $this->adminUserId;
    }

    public function setAdminUserId(?string $adminUserId): void
    {
        $this->adminUserId = $adminUserId;
    }

    public function getAdminUser(): ?\Shopware\Core\System\User\UserEntity
    {
        return $this->adminUser;
    }

    public function setAdminUser(?\Shopware\Core\System\User\UserEntity $adminUser): void
    {
        $this->adminUser = $adminUser;
    }

    public function getAction(): ?AuditLogAction
    {
        return $this->action;
    }

    public function setAction(AuditLogAction $action): void
    {
        $this->action = $action;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): void
    {
        $this->oldValue = $oldValue;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): void
    {
        $this->newValue = $newValue;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }
}
