<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Fakes;

use Nomba\Sdk\Contracts\HttpClientInterface;

final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<int, array{method: string, uri: string, options: array<string, mixed>}> */
    public array $requests = [];

    /** @param array<string, mixed> $response */
    public function __construct(private array $response = []) {}

    /**
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $uri, array $options = []): array
    {
        $this->requests[] = compact('method', 'uri', 'options');

        return $this->response;
    }

    /** @param array<string, mixed> $response */
    public function queueResponse(array $response): void
    {
        $this->response = $response;
    }
}
