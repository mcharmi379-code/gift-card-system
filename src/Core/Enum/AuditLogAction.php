<?php

declare(strict_types=1);

namespace ICTECHGiftCard\Core\Enum;

enum AuditLogAction: string
{
    case Revoke = 'revoke';
    case BalanceAdjust = 'balance_adjust';
    case ManualResend = 'manual_resend';
    case StatusChange = 'status_change';
}
