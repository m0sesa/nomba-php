<?php

declare(strict_types=1);

namespace Nomba\Sdk\Resources;

use Nomba\Sdk\Contracts\HttpClientInterface;
use Nomba\Sdk\DTOs\PaginatedResponse;
use Nomba\Sdk\DTOs\VirtualAccountResponse;

final class VirtualAccountResource
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * Fetch a virtual account by its reference or account number.
     */
    public function find(string $identifier): VirtualAccountResponse
    {
        $response = $this->httpClient->request('GET', "/v1/accounts/virtual/{$identifier}");

        return VirtualAccountResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * `limit` and `cursor` are query parameters; all other fields are POST body filters.
     *
     * @param array{
     *     limit?: int,
     *     cursor?: string,
     *     accountName?: string,
     *     accountRef?: string,
     *     bvn?: string,
     *     bankAccountNumber?: string,
     *     dateCreatedFrom?: string,
     *     dateCreatedTo?: string,
     *     expired?: bool,
     * } $filters
     */
    public function list(array $filters = []): PaginatedResponse
    {
        $query   = array_filter([
            'limit'  => $filters['limit']  ?? null,
            'cursor' => $filters['cursor'] ?? null,
        ], static fn ($v) => $v !== null);
        $payload = array_diff_key($filters, ['limit' => true, 'cursor' => true]);

        $response = $this->httpClient->request('POST', '/v1/accounts/virtual/list', [
            'payload' => $payload,
            'query'   => $query,
        ]);

        return PaginatedResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Create a new virtual account.
     *
     * @param array{
     *     accountRef: string,
     *     accountName: string,
     *     bvn?: string,
     *     expiryDate?: string,
     *     expectedAmount?: int|float,
     * } $payload
     */
    public function create(array $payload): VirtualAccountResponse
    {
        $response = $this->httpClient->request('POST', '/v1/accounts/virtual', ['payload' => $payload]);

        return VirtualAccountResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Update a virtual account (e.g. extend expiry, change expected amount).
     *
     * @param array{
     *     accountName?: string,
     *     expiryDate?: string,
     *     expectedAmount?: int|float,
     *     callbackUrl?: string,
     * } $payload
     */
    public function update(string $accountRef, array $payload): VirtualAccountResponse
    {
        $response = $this->httpClient->request('PUT', "/v1/accounts/virtual/{$accountRef}", ['payload' => $payload]);

        return VirtualAccountResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Immediately expire a virtual account so it no longer accepts payments.
     */
    public function expire(string $accountRef): bool
    {
        $response = $this->httpClient->request('PUT', "/v1/accounts/virtual/{$accountRef}/expire");

        return ($response['code'] ?? '') === '00';
    }
}
