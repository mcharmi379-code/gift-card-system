<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCardVoucher;

use ICTECHGiftCard\Core\Content\GiftCard\GiftCardEntity;
use ICTECHGiftCard\Core\Content\GiftCardAuditLog\GiftCardAuditLogCollection;
use ICTECHGiftCard\Core\Content\GiftCardTransaction\GiftCardTransactionCollection;
use ICTECHGiftCard\Core\Enum\VoucherStatus;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Currency\CurrencyEntity;

class GiftCardVoucherEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $giftCardId = '';

    protected ?GiftCardEntity $giftCard = null;

    protected ?string $orderId = null;

    protected ?string $orderVersionId = null;

    protected ?OrderEntity $order = null;

    protected ?string $orderLineItemId = null;

    protected ?string $customerId = null;

    protected ?CustomerEntity $customer = null;

    protected string $code = '';

    protected float $originalAmount = 0.0;

    protected float $remainingBalance = 0.0;

    protected string $currencyId = '';

    protected ?CurrencyEntity $currency = null;

    protected string $senderName = '';

    protected string $recipientName = '';

    protected string $recipientEmail = '';

    protected ?string $personalMessage = null;

    protected ?\DateTimeInterface $scheduledSendDate = null;

    protected ?\DateTimeInterface $sentAt = null;

    protected ?\DateTimeInterface $expiresAt = null;

    protected ?string $usedInOrderNumber = null;

    protected VoucherStatus $status = VoucherStatus::WaitingValidOrder;

    protected ?GiftCardTransactionCollection $transactions = null;

    protected ?GiftCardAuditLogCollection $auditLogs = null;

    public function getGiftCardId(): string
    {
        return $this->giftCardId;
    }

    public function setGiftCardId(string $giftCardId): void
    {
        $this->giftCardId = $giftCardId;
    }

    public function getGiftCard(): ?GiftCardEntity
    {
        return $this->giftCard;
    }

    public function setGiftCard(?GiftCardEntity $giftCard): void
    {
        $this->giftCard = $giftCard;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderLineItemId(): ?string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(?string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getOriginalAmount(): float
    {
        return $this->originalAmount;
    }

    public function setOriginalAmount(float $originalAmount): void
    {
        $this->originalAmount = $originalAmount;
    }

    public function getRemainingBalance(): float
    {
        return $this->remainingBalance;
    }

    public function setRemainingBalance(float $remainingBalance): void
    {
        $this->remainingBalance = $remainingBalance;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(?CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): void
    {
        $this->senderName = $senderName;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function setRecipientName(string $recipientName): void
    {
        $this->recipientName = $recipientName;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(string $recipientEmail): void
    {
        $this->recipientEmail = $recipientEmail;
    }

    public function getPersonalMessage(): ?string
    {
        return $this->personalMessage;
    }

    public function setPersonalMessage(?string $personalMessage): void
    {
        $this->personalMessage = $personalMessage;
    }

    public function getScheduledSendDate(): ?\DateTimeInterface
    {
        return $this->scheduledSendDate;
    }

    public function setScheduledSendDate(\DateTimeInterface $scheduledSendDate): void
    {
        $this->scheduledSendDate = $scheduledSendDate;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): void
    {
        $this->sentAt = $sentAt;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getUsedInOrderNumber(): ?string
    {
        return $this->usedInOrderNumber;
    }

    public function setUsedInOrderNumber(?string $usedInOrderNumber): void
    {
        $this->usedInOrderNumber = $usedInOrderNumber;
    }

    public function getStatus(): VoucherStatus
    {
        return $this->status;
    }

    public function setStatus(VoucherStatus $status): void
    {
        $this->status = $status;
    }

    public function getTransactions(): ?GiftCardTransactionCollection
    {
        return $this->transactions;
    }

    public function setTransactions(GiftCardTransactionCollection $transactions): void
    {
        $this->transactions = $transactions;
    }

    public function getAuditLogs(): ?GiftCardAuditLogCollection
    {
        return $this->auditLogs;
    }

    public function setAuditLogs(GiftCardAuditLogCollection $auditLogs): void
    {
        $this->auditLogs = $auditLogs;
    }
}
