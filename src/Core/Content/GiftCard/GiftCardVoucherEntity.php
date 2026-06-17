<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Content\GiftCard;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class GiftCardVoucherEntity extends Entity
{
    use EntityIdTrait;

    protected string $giftCardId;
    protected ?string $orderId = null;
    protected ?string $customerId = null;
    protected string $code;
    protected float $originalAmount;
    protected float $remainingBalance;
    protected string $currencyId;
    protected string $senderName = '';
    protected string $recipientName;
    protected string $recipientEmail;
    protected ?string $personalMessage = null;
    protected \DateTimeInterface $scheduledSendDate;
    protected ?\DateTimeInterface $sentAt = null;
    protected \DateTimeInterface $expiresAt;
    protected string $status;

    public function getGiftCardId(): string { return $this->giftCardId; }
    public function getOrderId(): ?string { return $this->orderId; }
    public function getCustomerId(): ?string { return $this->customerId; }
    public function getCode(): string { return $this->code; }
    public function getOriginalAmount(): float { return $this->originalAmount; }
    public function getRemainingBalance(): float { return $this->remainingBalance; }
    public function getCurrencyId(): string { return $this->currencyId; }
    public function getSenderName(): string { return $this->senderName; }
    public function getRecipientName(): string { return $this->recipientName; }
    public function getRecipientEmail(): string { return $this->recipientEmail; }
    public function getPersonalMessage(): ?string { return $this->personalMessage; }
    public function getScheduledSendDate(): \DateTimeInterface { return $this->scheduledSendDate; }
    public function getSentAt(): ?\DateTimeInterface { return $this->sentAt; }
    public function getExpiresAt(): \DateTimeInterface { return $this->expiresAt; }
    public function getStatus(): string { return $this->status; }
}
