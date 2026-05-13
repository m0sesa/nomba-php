<?php

declare(strict_types=1);

namespace Nomba\Sdk\Enums;

enum TransferStatus: string
{
    case Success        = 'SUCCESS';
    case PendingBilling = 'PENDING_BILLING';
    case Refund         = 'REFUND';
    case Unknown        = 'unknown';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }
}
