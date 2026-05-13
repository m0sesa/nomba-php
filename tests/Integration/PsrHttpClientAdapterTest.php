<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Integration;

use Nomba\Sdk\Exceptions\ApiException;
use Nomba\Sdk\Exceptions\AuthenticationException;
use Nomba\Sdk\Exceptions\RateLimitException;
use Nomba\Sdk\Exceptions\ValidationException;
use Nomba\Sdk\Http\PsrHttpClientAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class PsrHttpClientAdapterTest extends TestCase
{
    private function response(int $status, array $body): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn((string) json_encode($body));
        $stream->method('getContents')->willReturn((string) json_encode($body));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturn([]);

        return $response;
    }

    private function adapter(ResponseInterface $mockedResponse, ?callable $tokenProvider = null): PsrHttpClientAdapter
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();

        $psrClient = $this->createMock(ClientInterface::class);
        $psrClient->method('sendRequest')->willReturn($mockedResponse);

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        return new PsrHttpClientAdapter($psrClient, $requestFactory, $streamFactory, $tokenProvider);
    }

    public function test_successful_response_is_decoded(): void
    {
        $result = $this->adapter($this->response(200, ['data' => ['id' => 'ord_1']]))->request('GET', '/v1/checkout');

        self::assertSame('ord_1', $result['data']['id']);
    }

    public function test_throws_authentication_exception_on_401(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->adapter($this->response(401, ['description' => 'Unauthorized']))->request('GET', '/');
    }

    public function test_throws_authentication_exception_on_403(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->adapter($this->response(403, ['description' => 'Forbidden']))->request('GET', '/');
    }

    public function test_throws_validation_exception_on_422(): void
    {
        try {
            $this->adapter($this->response(422, ['description' => 'Validation failed.', 'errors' => ['amount' => ['Required.']]]))->request('POST', '/');
            self::fail('ValidationException not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('amount', $e->errors());
        }
    }

    public function test_throws_rate_limit_exception_on_429(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn((string) json_encode(['description' => 'Rate limited.']));
        $stream->method('getContents')->willReturn((string) json_encode(['description' => 'Rate limited.']));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(429);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturnMap([
            ['X-Rate-Limit-Limit', ['40']],
            ['X-Rate-Limit-Remaining', ['0']],
            ['X-Rate-Limit-Window', ['1s']],
        ]);

        try {
            $this->adapter($response)->request('GET', '/');
            self::fail('RateLimitException not thrown.');
        } catch (RateLimitException $e) {
            self::assertSame('40', $e->limit());
            self::assertSame(1, $e->retryAfter());
        }
    }

    public function test_throws_api_exception_on_unhandled_4xx(): void
    {
        $this->expectException(ApiException::class);

        $this->adapter($this->response(400, ['description' => 'Bad request.']))->request('GET', '/');
    }

    public function test_query_params_are_appended_to_uri(): void
    {
        $capturedUri = null;

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();

        $psrClient = $this->createMock(ClientInterface::class);
        $psrClient->method('sendRequest')->willReturn($this->response(200, []));

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturnCallback(
            function (string $method, string $uri) use ($request, &$capturedUri): RequestInterface {
                $capturedUri = $uri;
                return $request;
            }
        );

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        $adapter = new PsrHttpClientAdapter(
            $psrClient,
            $requestFactory,
            $streamFactory,
            baseUri: 'https://api.nomba.com',
        );

        $adapter->request('GET', '/v1/accounts', ['query' => ['limit' => 10, 'cursor' => 'abc']]);

        self::assertStringContainsString('limit=10', (string) $capturedUri);
        self::assertStringContainsString('cursor=abc', (string) $capturedUri);
    }

    public function test_query_params_are_not_appended_when_empty(): void
    {
        $capturedUri = null;

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();

        $psrClient = $this->createMock(ClientInterface::class);
        $psrClient->method('sendRequest')->willReturn($this->response(200, []));

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturnCallback(
            function (string $method, string $uri) use ($request, &$capturedUri): RequestInterface {
                $capturedUri = $uri;
                return $request;
            }
        );

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        $adapter = new PsrHttpClientAdapter(
            $psrClient,
            $requestFactory,
            $streamFactory,
            baseUri: 'https://api.nomba.com',
        );

        $adapter->request('GET', '/v1/accounts');

        self::assertStringNotContainsString('?', (string) $capturedUri);
    }

    public function test_token_provider_injects_authorization_header(): void
    {
        $captured = [];

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnCallback(function (string $name, string $value) use (&$captured, $request) {
            $captured[$name] = $value;
            return $request;
        });
        $request->method('withBody')->willReturnSelf();

        $psrClient = $this->createMock(ClientInterface::class);
        $psrClient->method('sendRequest')->willReturn($this->response(200, []));

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));

        $adapter = new PsrHttpClientAdapter($psrClient, $requestFactory, $streamFactory, fn () => 'my-token');
        $adapter->request('GET', '/test');

        self::assertSame('Bearer my-token', $captured['Authorization']);
    }
}
