<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Message;

final class SendGiftCardMailMessage
{
    public function __construct(
        private readonly string $voucherId,
        private readonly string $deliveryMethod,
        private readonly string $purchaserEmail,
        private readonly string $purchaserName,
        private readonly string $scheduledSendDate,
        private readonly string $recipientEmail,
        private readonly string $languageId,
        private readonly string $currencyId,
    ) {
    }

    public function getVoucherId(): string
    {
        return $this->voucherId;
    }

    public function getDeliveryMethod(): string
    {
        return $this->deliveryMethod;
    }

    public function getPurchaserEmail(): string
    {
        return $this->purchaserEmail;
    }

    public function getPurchaserName(): string
    {
        return $this->purchaserName;
    }

    public function getScheduledSendDate(): string
    {
        return $this->scheduledSendDate;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }
}
