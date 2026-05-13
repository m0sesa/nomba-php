<?php

declare(strict_types=1);

namespace Nomba\Sdk\Support;

use Nomba\Sdk\Enums\Environment;

final readonly class NombaConfig
{
    public function __construct(
        public string      $clientId,
        public string      $clientSecret,
        public string      $accountId,
        public Environment $environment   = Environment::Sandbox,
        public float       $timeout       = 30.0,
        public int         $retryAttempts = 3,
        public string      $webhookSecret = '',
    ) {}

    public function baseUrl(): string
    {
        return $this->environment->baseUrl();
    }
}
