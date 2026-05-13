<?php

declare(strict_types=1);

namespace Nomba\Sdk\Enums;

enum WalletStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Frozen   = 'frozen';
    case Unknown  = 'unknown';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }
}
