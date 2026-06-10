<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Enum;

enum VoucherStatus: string
{
    case WaitingValidOrder = 'waiting_valid_order';
    case Unused             = 'unused';
    case Used               = 'used';
    case Canceled           = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::WaitingValidOrder => 'Waiting valid order',
            self::Unused            => 'Unused',
            self::Used              => 'Used',
            self::Canceled          => 'Canceled',
        };
    }
}
