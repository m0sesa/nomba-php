<?php

declare(strict_types=1);

namespace Nomba\Sdk\Contracts;

interface HttpClientInterface
{
    /**
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $uri, array $options = []): array;
}
