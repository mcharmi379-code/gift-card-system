<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardTransaction;

use ICTECHGiftCard\Core\Content\GiftCardVoucher\GiftCardVoucherEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class GiftCardTransactionEntity extends Entity
{
    use EntityIdTrait;

    protected string $voucherId = '';

    protected ?GiftCardVoucherEntity $voucher = null;

    protected ?string $orderId = null;

    protected ?string $orderVersionId = null;

    protected ?OrderEntity $order = null;

    protected ?string $customerId = null;

    protected ?CustomerEntity $customer = null;

    protected float $amountUsed = 0.0;

    protected float $balanceBefore = 0.0;

    protected float $balanceAfter = 0.0;

    public function getVoucherId(): string
    {
        return $this->voucherId;
    }

    public function setVoucherId(string $voucherId): void
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

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): ?string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(?string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getAmountUsed(): float
    {
        return $this->amountUsed;
    }

    public function setAmountUsed(float $amountUsed): void
    {
        $this->amountUsed = $amountUsed;
    }

    public function getBalanceBefore(): float
    {
        return $this->balanceBefore;
    }

    public function setBalanceBefore(float $balanceBefore): void
    {
        $this->balanceBefore = $balanceBefore;
    }

    public function getBalanceAfter(): float
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(float $balanceAfter): void
    {
        $this->balanceAfter = $balanceAfter;
    }
}
