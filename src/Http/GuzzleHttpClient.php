<?php

declare(strict_types=1);

namespace Nomba\Sdk\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use Nomba\Sdk\Contracts\HttpClientInterface;
use Nomba\Sdk\Enums\Environment;
use Nomba\Sdk\Exceptions\ApiException;
use Nomba\Sdk\Exceptions\AuthenticationException;
use Nomba\Sdk\Exceptions\NetworkException;
use Nomba\Sdk\Exceptions\RateLimitException;
use Nomba\Sdk\Exceptions\ValidationException;
use Nomba\Sdk\Support\NombaConfig;
use Psr\Http\Message\ResponseInterface;

final class GuzzleHttpClient implements HttpClientInterface
{
    private readonly Client $client;

    /**
     * @param array<int, callable> $middleware
     * @param array<string, mixed> $guzzleConfig
     */
    public function __construct(
        private readonly NombaConfig   $config,
        private readonly Authenticator $authenticator,
        array $middleware   = [],
        array $guzzleConfig = [],
    ) {
        $stack = HandlerStack::create();
        $stack->push($this->retryMiddleware());

        foreach ($middleware as $layer) {
            $stack->push($layer);
        }

        $this->client = new Client(array_replace_recursive([
            'base_uri' => $config->baseUrl(),
            'timeout'  => $config->timeout,
            'handler'  => $stack,
        ], $guzzleConfig));
    }

    /**
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $uri, array $options = []): array
    {
        $options = $this->withAuthHeaders($options);

        try {
            $response = $this->client->request($method, $uri, $options);

            return $this->decode($response);
        } catch (AuthenticationException|ValidationException|RateLimitException|ApiException|NetworkException $e) {
            throw $e;
        } catch (ConnectException $e) {
            throw new NetworkException($e->getMessage(), (int) $e->getCode(), $e);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response !== null) {
                $this->throwMapped($response);
            }
            throw new NetworkException($e->getMessage(), (int) $e->getCode(), $e);
        } catch (GuzzleException $e) {
            throw new NetworkException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @param  array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function withAuthHeaders(array $options): array
    {
        $existing = \is_array($options['headers'] ?? null) ? $options['headers'] : [];

        $options['headers'] = array_merge($existing, [
            'accountId'    => $this->config->accountId,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        if ($this->config->environment !== Environment::Sandbox) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->authenticator->getValidToken();
        }

        if (isset($options['payload']) && \is_array($options['payload'])) {
            $options[RequestOptions::JSON] = $options['payload'];
            unset($options['payload']);
        }

        return $options;
    }

    private function retryMiddleware(): callable
    {
        $maxRetries = $this->config->retryAttempts;

        return Middleware::retry(
            static function (
                int $retries,
                mixed $request,
                ?ResponseInterface $response = null,
                ?\Throwable $exception = null,
            ) use ($maxRetries): bool {
                if ($retries >= $maxRetries) {
                    return false;
                }

                if ($exception instanceof ConnectException) {
                    return true;
                }

                return $response !== null && $response->getStatusCode() >= 500;
            },
            static fn (int $retries): int => 1000 * $retries,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $payload = json_decode((string) $response->getBody(), true);

        return \is_array($payload) ? $payload : [];
    }

    private function throwMapped(ResponseInterface $response): never
    {
        $status  = $response->getStatusCode();
        $payload = $this->decode($response);
        $message = (string) ($payload['description'] ?? $payload['message'] ?? 'Unexpected API error.');

        if ($status === 401 || $status === 403) {
            throw new AuthenticationException($message, $status);
        }

        if ($status === 422) {
            throw new ValidationException($message, (array) ($payload['errors'] ?? []), $status);
        }

        if ($status === 429) {
            throw new RateLimitException(
                $message,
                $response->getHeader('X-Rate-Limit-Limit')[0]     ?? null,
                $response->getHeader('X-Rate-Limit-Remaining')[0] ?? null,
                $response->getHeader('X-Rate-Limit-Window')[0]    ?? null,
            );
        }

        throw new ApiException($message, $status, $payload);
    }
}
