<?php

declare(strict_types=1);

namespace Nomba\Sdk\Resources;

use Nomba\Sdk\Contracts\HttpClientInterface;
use Nomba\Sdk\DTOs\TransferResponse;

final class TransferResource
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * @param array{
     *     amount: int|float,
     *     accountNumber: string,
     *     accountName: string,
     *     bankCode: string,
     *     merchantTxRef: string,
     *     senderName: string,
     *     narration?: string,
     * } $payload
     * @param string|null $idempotencyKey  Unique key to safely retry on network failures without duplicate transfers.
     */
    public function bankTransfer(array $payload, ?string $idempotencyKey = null): TransferResponse
    {
        $options = ['payload' => $payload];

        if ($idempotencyKey !== null) {
            $options['headers']['X-Idempotent-key'] = $idempotencyKey;
        }

        $response = $this->httpClient->request('POST', '/v2/transfers/bank', $options);

        return TransferResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * @param array{
     *     amount: int|float,
     *     receiverAccountId: string,
     *     merchantTxRef: string,
     *     narration?: string,
     * } $payload
     * @param string|null $idempotencyKey  Unique key to safely retry on network failures without duplicate transfers.
     */
    public function walletTransfer(array $payload, ?string $idempotencyKey = null): TransferResponse
    {
        $options = ['payload' => $payload];

        if ($idempotencyKey !== null) {
            $options['headers']['X-Idempotent-key'] = $idempotencyKey;
        }

        $response = $this->httpClient->request('POST', '/v2/transfers/wallet', $options);

        return TransferResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Look up an account name before initiating a transfer.
     *
     * @param array{accountNumber: string, bankCode: string} $payload
     * @return array{accountName: string, accountNumber: string, bankCode: string}
     */
    public function lookupBankAccount(array $payload): array
    {
        $response = $this->httpClient->request('POST', '/v1/transfers/bank/lookup', [
            'payload' => $payload,
        ]);

        return (array) ($response['data'] ?? []);
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public function banks(): array
    {
        $response = $this->httpClient->request('GET', '/v1/transfers/banks');

        return (array) ($response['data']['results'] ?? []);
    }
}
