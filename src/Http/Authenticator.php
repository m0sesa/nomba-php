<?php

declare(strict_types=1);

namespace Nomba\Sdk\Http;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Nomba\Sdk\Exceptions\AuthenticationException;
use Nomba\Sdk\Support\NombaConfig;
use Psr\SimpleCache\CacheInterface;

/**
 * Manages OAuth2 client-credentials tokens for Nomba.
 *
 * Token priority:
 *   1. PSR-16 cache (survives across PHP-FPM requests when injected)
 *   2. In-memory (default; works for Octane / queue workers / CLI)
 *   3. Refresh via refresh_token (in-memory only)
 *   4. Re-issue via client_credentials
 */
final class Authenticator
{
    private ?string           $accessToken  = null;
    private ?string           $refreshToken = null;
    private ?DateTimeImmutable $expiresAt   = null;

    public function __construct(
        private readonly NombaConfig      $config,
        private readonly Client           $guzzle,
        private readonly ?CacheInterface  $cache = null,
    ) {}

    public function getValidToken(): string
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get($this->cacheKey());
            if (\is_string($cached) && $cached !== '') {
                return $cached;
            }
        } elseif ($this->isMemoryTokenFresh()) {
            return $this->accessToken;
        }

        if ($this->refreshToken !== null) {
            try {
                return $this->refresh();
            } catch (AuthenticationException) {
                // refresh_token expired — fall through to re-issue
            }
        }

        return $this->issue();
    }

    private function isMemoryTokenFresh(): bool
    {
        return $this->accessToken !== null
            && $this->expiresAt !== null
            && $this->expiresAt > new DateTimeImmutable('+30 seconds');
    }

    private function issue(): string
    {
        return $this->fetchToken('/v1/auth/token/issue', [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
        ]);
    }

    private function refresh(): string
    {
        return $this->fetchToken('/v1/auth/token/refresh', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ], $this->accessToken !== null ? ['Authorization' => 'Bearer ' . $this->accessToken] : []);
    }

    /**
     * @param array<string, string> $body
     * @param array<string, string> $extraHeaders
     */
    private function fetchToken(string $endpoint, array $body, array $extraHeaders = []): string
    {
        try {
            $response = $this->guzzle->post($endpoint, [
                RequestOptions::JSON    => $body,
                RequestOptions::HEADERS => array_merge([
                    'accountId'    => $this->config->accountId,
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ], $extraHeaders),
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            $data    = $payload['data'] ?? [];

            $token        = (string) ($data['access_token'] ?? '');
            $refreshToken = isset($data['refresh_token']) ? (string) $data['refresh_token'] : null;
            $expiresAt    = isset($data['expiresAt']) ? new DateTimeImmutable((string) $data['expiresAt']) : null;

            if ($token === '') {
                throw new AuthenticationException('Token response did not include an access_token.');
            }

            $this->accessToken  = $token;
            $this->refreshToken = $refreshToken;
            $this->expiresAt    = $expiresAt;

            if ($this->cache !== null && $expiresAt !== null) {
                $ttl = max(1, $expiresAt->getTimestamp() - time() - 30);
                $this->cache->set($this->cacheKey(), $token, $ttl);
            }

            return $token;
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new AuthenticationException(
                'Failed to obtain access token: ' . $e->getMessage(),
                401,
                $e,
            );
        }
    }

    private function cacheKey(): string
    {
        return 'nomba_token_' . md5($this->config->clientId);
    }
}
