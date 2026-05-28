<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Resources;

use Nomba\Sdk\DTOs\TransferResponse;
use Nomba\Sdk\Enums\TransferStatus;
use Nomba\Sdk\Resources\TransferResource;
use Nomba\Sdk\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class TransferResourceTest extends TestCase
{
    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function data(array $override = []): array
    {
        return [
            'id'          => 'txn_001',
            'status'      => 'SUCCESS',
            'type'        => 'bank',
            'amount'      => 10000.0,
            'fee'         => 50.0,
            'timeCreated' => '2026-01-01T00:00:00+00:00',
            'meta'        => [],
            ...$override,
        ];
    }

    public function test_bank_transfer_returns_transfer_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->data()]);
        $response = (new TransferResource($client))->bankTransfer(['amount' => 10000, 'accountNumber' => '0123456789', 'accountName' => 'John Doe', 'bankCode' => '058', 'merchantTxRef' => 'ref_001', 'senderName' => 'Jane']);

        self::assertInstanceOf(TransferResponse::class, $response);
        self::assertSame('txn_001', $response->id);
        self::assertSame(TransferStatus::Success, $response->status);
        self::assertSame(10000.0, $response->amount);
        self::assertSame(50.0, $response->fee);
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v2/transfers/bank', $client->requests[0]['uri']);
    }

    public function test_bank_transfer_sends_idempotency_key_header(): void
    {
        $client = new FakeHttpClient(['data' => $this->data()]);
        (new TransferResource($client))->bankTransfer(['amount' => 10000, 'accountNumber' => '0123456789', 'accountName' => 'John Doe', 'bankCode' => '058', 'merchantTxRef' => 'ref_001', 'senderName' => 'Jane'], 'idem-key-001');

        self::assertSame('idem-key-001', $client->requests[0]['options']['headers']['X-Idempotent-key']);
    }

    public function test_bank_transfer_omits_idempotency_header_when_null(): void
    {
        $client = new FakeHttpClient(['data' => $this->data()]);
        (new TransferResource($client))->bankTransfer(['amount' => 10000, 'accountNumber' => '0123456789', 'accountName' => 'John Doe', 'bankCode' => '058', 'merchantTxRef' => 'ref_001', 'senderName' => 'Jane']);

        self::assertArrayNotHasKey('headers', $client->requests[0]['options']);
    }

    public function test_transfer_status_maps_pending_billing(): void
    {
        $client   = new FakeHttpClient(['data' => $this->data(['status' => 'PENDING_BILLING'])]);
        $response = (new TransferResource($client))->bankTransfer(['amount' => 10000, 'accountNumber' => '0123456789', 'accountName' => 'John Doe', 'bankCode' => '058', 'merchantTxRef' => 'ref_001', 'senderName' => 'Jane']);

        self::assertSame(TransferStatus::PendingBilling, $response->status);
    }

    public function test_transfer_status_maps_refund(): void
    {
        $client   = new FakeHttpClient(['data' => $this->data(['status' => 'REFUND'])]);
        $response = (new TransferResource($client))->bankTransfer(['amount' => 10000, 'accountNumber' => '0123456789', 'accountName' => 'John Doe', 'bankCode' => '058', 'merchantTxRef' => 'ref_001', 'senderName' => 'Jane']);

        self::assertSame(TransferStatus::Refund, $response->status);
    }

    public function test_transfer_status_falls_back_to_unknown(): void
    {
        $client   = new FakeHttpClient(['data' => $this->data(['status' => 'SOMETHING_NEW'])]);
        $response = (new TransferResource($client))->bankTransfer(['amount' => 10000, 'accountNumber' => '0123456789', 'accountName' => 'John Doe', 'bankCode' => '058', 'merchantTxRef' => 'ref_001', 'senderName' => 'Jane']);

        self::assertSame(TransferStatus::Unknown, $response->status);
    }

    public function test_wallet_transfer_returns_transfer_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->data(['type' => 'wallet'])]);
        $response = (new TransferResource($client))->walletTransfer(['amount' => 5000, 'receiverAccountId' => 'acct_002', 'merchantTxRef' => 'ref_002']);

        self::assertInstanceOf(TransferResponse::class, $response);
        self::assertSame('wallet', $response->type);
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v2/transfers/wallet', $client->requests[0]['uri']);
    }

    public function test_wallet_transfer_sends_idempotency_key_header(): void
    {
        $client = new FakeHttpClient(['data' => $this->data()]);
        (new TransferResource($client))->walletTransfer(['amount' => 5000, 'receiverAccountId' => 'acct_002', 'merchantTxRef' => 'ref_002'], 'idem-key-002');

        self::assertSame('idem-key-002', $client->requests[0]['options']['headers']['X-Idempotent-key']);
    }

    public function test_banks_returns_array_of_bank_codes(): void
    {
        $client = new FakeHttpClient([
            'data' => [
                'results' => [
                    ['code' => '058', 'name' => 'Guaranty Trust Bank'],
                    ['code' => '011', 'name' => 'First Bank of Nigeria'],
                ],
            ],
        ]);

        $banks = (new TransferResource($client))->banks();

        self::assertCount(2, $banks);
        self::assertSame('058', $banks[0]['code']);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/transfers/banks', $client->requests[0]['uri']);
    }

    public function test_lookup_bank_account_posts_payload(): void
    {
        $client = new FakeHttpClient(['data' => ['accountName' => 'John Doe', 'accountNumber' => '0123456789', 'bankCode' => '058']]);
        $result = (new TransferResource($client))->lookupBankAccount(['accountNumber' => '0123456789', 'bankCode' => '058']);

        self::assertSame('John Doe', $result['accountName']);
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/transfers/bank/lookup', $client->requests[0]['uri']);
        self::assertSame(['accountNumber' => '0123456789', 'bankCode' => '058'], $client->requests[0]['options']['payload']);
    }
}
