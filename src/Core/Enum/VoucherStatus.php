<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Enum;

enum VoucherStatus: string
{
    case WaitingValidOrder = 'waiting_valid_order';
    case Unused = 'unused';
    case PartiallyUsed = 'partially_used';
    case Used = 'used';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::WaitingValidOrder => 'Waiting valid order',
            self::Unused => 'Unused',
            self::PartiallyUsed => 'Partially used',
            self::Used => 'Used',
            self::Canceled => 'Canceled',
            self::Expired => 'Expired',
        };
    }
}
