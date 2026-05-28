<?php

declare(strict_types=1);

namespace Nomba\Sdk\Http;

use Nomba\Sdk\Contracts\HttpClientInterface;
use Nomba\Sdk\Exceptions\ApiException;
use Nomba\Sdk\Exceptions\AuthenticationException;
use Nomba\Sdk\Exceptions\NetworkException;
use Nomba\Sdk\Exceptions\RateLimitException;
use Nomba\Sdk\Exceptions\ValidationException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Wraps any PSR-18 HTTP client into the SDK's HttpClientInterface.
 *
 * Authentication: pass a callable $tokenProvider that returns the current
 * Bearer token string. If omitted, no Authorization header is injected.
 *
 * Example:
 *   new PsrHttpClientAdapter($client, $requestFactory, $streamFactory,
 *       tokenProvider: fn () => $myAuthService->getToken(),
 *       baseUri: 'https://api.nomba.com',
 *       accountId: 'acct_xxx',
 *   );
 */
final class PsrHttpClientAdapter implements HttpClientInterface
{
    public function __construct(
        private readonly ClientInterface         $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface  $streamFactory,
        /** @var (callable(): (string|null))|null */
        private readonly mixed                   $tokenProvider = null,
        private readonly string                  $baseUri       = '',
        private readonly string                  $accountId     = '',
    ) {}

    /**
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $uri, array $options = []): array
    {
        try {
            $fullUri = $this->baseUri !== '' && !str_starts_with($uri, 'http')
                ? rtrim($this->baseUri, '/') . $uri
                : $uri;

            if (!empty($options['query']) && \is_array($options['query'])) {
                $separator = str_contains($fullUri, '?') ? '&' : '?';
                $fullUri  .= $separator . http_build_query($options['query']);
            }

            $request = $this->requestFactory->createRequest($method, $fullUri);

            if ($this->tokenProvider !== null) {
                $token = ($this->tokenProvider)();
                if ($token !== null && $token !== '') {
                    $request = $request->withHeader('Authorization', 'Bearer ' . $token);
                }
            }

            if ($this->accountId !== '') {
                $request = $request->withHeader('accountId', $this->accountId);
            }

            $request = $request
                ->withHeader('Accept', 'application/json')
                ->withHeader('Content-Type', 'application/json');

            $headers = \is_array($options['headers'] ?? null) ? $options['headers'] : [];
            foreach ($headers as $name => $value) {
                $request = $request->withHeader((string) $name, (string) $value);
            }

            $body = $options['json'] ?? $options['payload'] ?? null;
            if ($body !== null) {
                $request = $request->withBody(
                    $this->streamFactory->createStream(
                        json_encode($body, JSON_THROW_ON_ERROR),
                    ),
                );
            }

            $response = $this->client->sendRequest($request);

            return $this->decode($response);
        } catch (AuthenticationException|ValidationException|RateLimitException|ApiException|NetworkException $e) {
            throw $e;
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), (int) $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new NetworkException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $status  = $response->getStatusCode();
        $payload = json_decode((string) $response->getBody(), true);
        $data    = \is_array($payload) ? $payload : [];

        if ($status >= 200 && $status < 300) {
            return $data;
        }

        $message = (string) ($data['description'] ?? $data['message'] ?? 'Unexpected API error.');

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($message, $status);
        }

        if ($status === 422) {
            throw new ValidationException($message, (array) ($data['errors'] ?? []), $status);
        }

        if ($status === 429) {
            throw new RateLimitException(
                $message,
                $response->getHeader('X-Rate-Limit-Limit')[0]     ?? null,
                $response->getHeader('X-Rate-Limit-Remaining')[0] ?? null,
                $response->getHeader('X-Rate-Limit-Window')[0]    ?? null,
            );
        }

        throw new ApiException($message, $status, $data);
    }
}
