<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

final class ICTECHGiftCardEmailTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'ictech_gift_card.send_emails';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
