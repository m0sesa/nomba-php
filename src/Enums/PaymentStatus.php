<?php

declare(strict_types=1);

namespace Nomba\Sdk\Enums;

enum PaymentStatus: string
{
    case Successful = 'PAYMENT_SUCCESSFUL';
    case Failed     = 'PAYMENT_FAILED';
    case Unknown    = 'unknown';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }
}
