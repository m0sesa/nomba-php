<?php

declare(strict_types=1);

namespace Nomba\Sdk\Resources;

use Nomba\Sdk\Contracts\HttpClientInterface;
use Nomba\Sdk\DTOs\AccountResponse;
use Nomba\Sdk\DTOs\BalanceResponse;
use Nomba\Sdk\DTOs\PaginatedResponse;

final class AccountResource
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * Fetch the authenticated parent (business) account details.
     */
    public function parent(): AccountResponse
    {
        $response = $this->httpClient->request('GET', '/v1/accounts/parent');

        return AccountResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Fetch the parent account wallet balance.
     */
    public function balance(): BalanceResponse
    {
        $response = $this->httpClient->request('GET', '/v1/accounts/balance');

        return BalanceResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Fetch a sub-account's wallet balance.
     */
    public function subAccountBalance(string $accountId): BalanceResponse
    {
        $response = $this->httpClient->request('GET', "/v1/accounts/{$accountId}/balance");

        return BalanceResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Fetch details for a specific sub-account.
     */
    public function subAccount(string $accountId): AccountResponse
    {
        $response = $this->httpClient->request('GET', '/v1/accounts/sub-account-details', [
            'query' => ['accountId' => $accountId],
        ]);

        return AccountResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * List all sub-accounts under the parent account.
     */
    public function list(int $limit = 50, string $cursor = ''): PaginatedResponse
    {
        $query = array_filter(['limit' => $limit, 'cursor' => $cursor], static fn ($v) => $v !== '' && $v !== 0);

        $response = $this->httpClient->request('GET', '/v1/accounts', ['query' => $query]);

        return PaginatedResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Create a new sub-account.
     *
     * @param array{
     *     email: string,
     *     name: string,
     *     currency?: string,
     * } $payload
     */
    public function create(array $payload): AccountResponse
    {
        $response = $this->httpClient->request('POST', '/v1/accounts', ['payload' => $payload]);

        return AccountResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * List terminals assigned to the parent account (paginated).
     */
    public function terminals(int $limit = 50, string $cursor = ''): PaginatedResponse
    {
        $query = array_filter(['limit' => $limit, 'cursor' => $cursor], static fn ($v) => $v !== '' && $v !== 0);

        $response = $this->httpClient->request('GET', '/v1/accounts/terminals', ['query' => $query]);

        return PaginatedResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * List terminals assigned to a specific sub-account (paginated).
     */
    public function subAccountTerminals(string $accountId, int $limit = 50, string $cursor = ''): PaginatedResponse
    {
        $query = array_filter(['limit' => $limit, 'cursor' => $cursor], static fn ($v) => $v !== '' && $v !== 0);

        $response = $this->httpClient->request('GET', "/v1/accounts/{$accountId}/terminals", ['query' => $query]);

        return PaginatedResponse::fromArray((array) ($response['data'] ?? []));
    }

}
