<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Nomba\Sdk\Enums\Environment;
use Nomba\Sdk\Exceptions\ApiException;
use Nomba\Sdk\Exceptions\AuthenticationException;
use Nomba\Sdk\Exceptions\NetworkException;
use Nomba\Sdk\Exceptions\RateLimitException;
use Nomba\Sdk\Exceptions\ValidationException;
use Nomba\Sdk\Http\Authenticator;
use Nomba\Sdk\Http\GuzzleHttpClient;
use Nomba\Sdk\Support\NombaConfig;
use PHPUnit\Framework\TestCase;

final class GuzzleHttpClientTest extends TestCase
{
    private function config(): NombaConfig
    {
        return new NombaConfig('client_id', 'client_secret', 'account_id', Environment::Sandbox);
    }

    private function authenticator(): Authenticator
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'data' => [
                    'access_token'  => 'test-token',
                    'refresh_token' => 'refresh-token',
                    'expiresAt'     => (new \DateTimeImmutable('+1 hour'))->format(\DateTimeInterface::ATOM),
                ],
            ])),
        ]);

        return new Authenticator(
            $this->config(),
            new Client(['handler' => HandlerStack::create($mock)]),
        );
    }

    private function client(MockHandler $mock, ?Authenticator $auth = null): GuzzleHttpClient
    {
        return new GuzzleHttpClient(
            $this->config(),
            $auth ?? $this->authenticator(),
            [],
            ['handler' => HandlerStack::create($mock)],
        );
    }

    public function test_it_decodes_a_successful_response(): void
    {
        $mock   = new MockHandler([new Response(200, [], (string) json_encode(['data' => ['id' => 'ord_1']]))]);
        $result = $this->client($mock)->request('GET', '/v1/checkout/order/ord_1');

        self::assertSame('ord_1', $result['data']['id']);
    }

    public function test_it_throws_authentication_exception_on_401(): void
    {
        $this->expectException(AuthenticationException::class);

        $mock = new MockHandler([
            new Response(401, [], (string) json_encode(['description' => 'Unauthorized'])),
        ]);

        $this->client($mock)->request('GET', '/v1/checkout/order/ord_1');
    }

    public function test_it_throws_authentication_exception_on_403(): void
    {
        $this->expectException(AuthenticationException::class);

        $mock = new MockHandler([
            new Response(403, [], (string) json_encode(['description' => 'Forbidden'])),
        ]);

        $this->client($mock)->request('GET', '/v1/checkout/order/ord_1');
    }

    public function test_it_throws_validation_exception_on_422(): void
    {
        $mock = new MockHandler([
            new Response(422, [], (string) json_encode([
                'description' => 'Validation failed.',
                'errors'      => ['amount' => ['The amount field is required.']],
            ])),
        ]);

        try {
            $this->client($mock)->request('POST', '/v1/checkout/order', ['payload' => []]);
            self::fail('ValidationException not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('amount', $e->errors());
        }
    }

    public function test_it_throws_rate_limit_exception_on_429_with_retry_after(): void
    {
        $mock = new MockHandler([
            new Response(429, [
                'X-Rate-Limit-Limit'     => '40',
                'X-Rate-Limit-Remaining' => '0',
                'X-Rate-Limit-Window'    => '1s',
            ], (string) json_encode(['description' => 'Too many requests.'])),
        ]);

        try {
            $this->client($mock)->request('GET', '/v1/checkout/order');
            self::fail('RateLimitException not thrown.');
        } catch (RateLimitException $e) {
            self::assertSame(429, $e->getCode());
            self::assertSame('40', $e->limit());
            self::assertSame('0', $e->remaining());
            self::assertSame('1s', $e->window());
            self::assertSame(1, $e->retryAfter());
        }
    }

    public function test_it_throws_api_exception_on_unhandled_4xx(): void
    {
        $this->expectException(ApiException::class);

        $mock = new MockHandler([
            new Response(400, [], (string) json_encode(['description' => 'Bad request.'])),
        ]);

        $this->client($mock)->request('GET', '/v1/checkout/order');
    }

    public function test_it_throws_network_exception_on_connection_failure(): void
    {
        $this->expectException(NetworkException::class);

        $mock = new MockHandler([
            new ConnectException('Connection refused.', new Request('GET', '/v1/checkout/order')),
        ]);

        // retryAttempts = 0 so it fails immediately
        $config = new NombaConfig('client_id', 'client_secret', 'account_id', Environment::Sandbox, 30.0, 0);
        $client = new GuzzleHttpClient($config, $this->authenticator(), [], ['handler' => HandlerStack::create($mock)]);
        $client->request('GET', '/v1/checkout/order');
    }

    public function test_it_throws_api_exception_after_exhausting_5xx_retries(): void
    {
        $this->expectException(ApiException::class);

        // retryAttempts = 1 — one retry, so two 500 responses needed
        $mock   = new MockHandler([
            new Response(500, [], (string) json_encode(['description' => 'Internal server error.'])),
            new Response(500, [], (string) json_encode(['description' => 'Internal server error.'])),
        ]);
        $config = new NombaConfig('client_id', 'client_secret', 'account_id', Environment::Sandbox, 30.0, 1);
        $client = new GuzzleHttpClient($config, $this->authenticator(), [], ['handler' => HandlerStack::create($mock)]);
        $client->request('GET', '/v1/checkout/order');
    }

    public function test_it_injects_authorization_and_account_id_headers_in_production(): void
    {
        $mock = new MockHandler([new Response(200, [], (string) json_encode([]))]);

        $container = [];
        $history   = \GuzzleHttp\Middleware::history($container);
        $stack     = HandlerStack::create($mock);
        $stack->push($history);

        $config = new NombaConfig('client_id', 'client_secret', 'account_id', Environment::Production);
        $client = new GuzzleHttpClient($config, $this->authenticator(), [], ['handler' => $stack]);
        $client->request('GET', '/v1/checkout/order');

        $sentRequest = $container[0]['request'];
        self::assertSame('Bearer test-token', $sentRequest->getHeaderLine('Authorization'));
        self::assertSame('account_id', $sentRequest->getHeaderLine('accountId'));
    }

    public function test_it_omits_authorization_header_in_sandbox(): void
    {
        $mock = new MockHandler([new Response(200, [], (string) json_encode([]))]);

        $container = [];
        $history   = \GuzzleHttp\Middleware::history($container);
        $stack     = HandlerStack::create($mock);
        $stack->push($history);

        $client = new GuzzleHttpClient($this->config(), $this->authenticator(), [], ['handler' => $stack]);
        $client->request('GET', '/v1/checkout/order');

        $sentRequest = $container[0]['request'];
        self::assertSame('', $sentRequest->getHeaderLine('Authorization'));
        self::assertSame('account_id', $sentRequest->getHeaderLine('accountId'));
    }
}
