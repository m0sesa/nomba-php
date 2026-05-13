<?php

declare(strict_types=1);

namespace Nomba\Sdk\Enums;

enum Environment: string
{
    case Sandbox    = 'sandbox';
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox    => 'https://sandbox.nomba.com',
            self::Production => 'https://api.nomba.com',
        };
    }
}
