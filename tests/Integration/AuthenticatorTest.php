<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nomba\Sdk\Enums\Environment;
use Nomba\Sdk\Exceptions\AuthenticationException;
use Nomba\Sdk\Http\Authenticator;
use Nomba\Sdk\Support\NombaConfig;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class AuthenticatorTest extends TestCase
{
    private function config(): NombaConfig
    {
        return new NombaConfig('client_id', 'client_secret', 'account_id', Environment::Sandbox);
    }

    private function tokenResponse(string $token = 'access-token-1', int $expiresInSeconds = 3600): Response
    {
        return new Response(200, [], (string) json_encode([
            'data' => [
                'access_token'  => $token,
                'refresh_token' => 'refresh-token',
                'expiresAt'     => (new \DateTimeImmutable("+{$expiresInSeconds} seconds"))->format(\DateTimeInterface::ATOM),
            ],
        ]));
    }

    private function authenticator(MockHandler $mock, ?CacheInterface $cache = null): Authenticator
    {
        return new Authenticator(
            $this->config(),
            new Client(['handler' => HandlerStack::create($mock)]),
            $cache,
        );
    }

    public function test_it_issues_a_token_on_first_call(): void
    {
        $mock  = new MockHandler([$this->tokenResponse('tok_abc')]);
        $token = $this->authenticator($mock)->getValidToken();

        self::assertSame('tok_abc', $token);
    }

    public function test_it_reuses_the_in_memory_token_without_extra_requests(): void
    {
        // Only one response queued — second call must use memory, not make a new request
        $mock          = new MockHandler([$this->tokenResponse('tok_abc')]);
        $authenticator = $this->authenticator($mock);

        $first  = $authenticator->getValidToken();
        $second = $authenticator->getValidToken();

        self::assertSame($first, $second);
        self::assertSame(0, $mock->count()); // queue exhausted after first call
    }

    public function test_it_stores_token_in_psr16_cache(): void
    {
        $mock  = new MockHandler([$this->tokenResponse('tok_cached')]);
        $cache = $this->createMock(CacheInterface::class);

        $cache->expects(self::once())->method('get')->willReturn(null);
        $cache->expects(self::once())->method('set')->with(
            self::stringContains('nomba_token_'),
            'tok_cached',
            self::greaterThan(0),
        );

        $this->authenticator($mock, $cache)->getValidToken();
    }

    public function test_it_retrieves_token_from_psr16_cache_without_issuing_new_one(): void
    {
        $mock  = new MockHandler([]); // no responses queued — must not make any HTTP call
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn('tok_from_cache');

        $token = $this->authenticator($mock, $cache)->getValidToken();

        self::assertSame('tok_from_cache', $token);
    }

    public function test_it_throws_authentication_exception_when_token_response_is_empty(): void
    {
        $this->expectException(AuthenticationException::class);

        $mock = new MockHandler([
            new Response(200, [], (string) json_encode(['data' => []])),
        ]);

        $this->authenticator($mock)->getValidToken();
    }

    public function test_it_throws_authentication_exception_on_failed_token_request(): void
    {
        $this->expectException(AuthenticationException::class);

        $mock = new MockHandler([
            new Response(401, [], (string) json_encode(['description' => 'Invalid credentials.'])),
        ]);

        $this->authenticator($mock)->getValidToken();
    }

    public function test_refresh_request_includes_authorization_header(): void
    {
        $container = [];
        $history   = \GuzzleHttp\Middleware::history($container);

        // First response: issues token with very short TTL so memory check fails fast.
        // We simulate an "about to expire" state by using a past expiry — but we can't
        // easily backdate, so instead we verify by calling getValidToken twice with a
        // token that expires in 1 second to observe the refresh path indirectly via
        // the Authorization header on the second POST request.
        $issued = new Response(200, [], (string) json_encode([
            'data' => [
                'access_token'  => 'first-token',
                'refresh_token' => 'refresh-token-abc',
                'expiresAt'     => (new \DateTimeImmutable('+1 hour'))->format(\DateTimeInterface::ATOM),
            ],
        ]));
        $refreshed = new Response(200, [], (string) json_encode([
            'data' => [
                'access_token'  => 'second-token',
                'refresh_token' => 'refresh-token-xyz',
                'expiresAt'     => (new \DateTimeImmutable('+1 hour'))->format(\DateTimeInterface::ATOM),
            ],
        ]));

        $mock  = new MockHandler([$issued, $refreshed]);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $auth = new Authenticator(
            $this->config(),
            new Client(['handler' => $stack]),
        );

        // Get first token — issues via client_credentials
        $first = $auth->getValidToken();
        self::assertSame('first-token', $first);

        // Force memory token to look stale via reflection (PHP 8.1+ needs no setAccessible)
        $prop = (new \ReflectionObject($auth))->getProperty('expiresAt');
        $prop->setValue($auth, new \DateTimeImmutable('-1 second'));

        // Now getValidToken should use refresh path
        $second = $auth->getValidToken();
        self::assertSame('second-token', $second);

        // Verify the refresh request carried Authorization: Bearer first-token
        $refreshRequest = $container[1]['request'];
        self::assertSame('Bearer first-token', $refreshRequest->getHeaderLine('Authorization'));
        self::assertStringContainsString('/refresh', $refreshRequest->getUri()->getPath());
    }
}
