<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Resources;

use Nomba\Sdk\DTOs\AccountResponse;
use Nomba\Sdk\DTOs\BalanceResponse;
use Nomba\Sdk\DTOs\PaginatedResponse;
use Nomba\Sdk\Resources\AccountResource;
use Nomba\Sdk\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class AccountResourceTest extends TestCase
{
    private function accountData(array $override = []): array
    {
        return array_merge([
            'accountId'       => 'acct_001',
            'accountHolderId' => 'holder_001',
            'accountRef'      => 'ref_001',
            'accountName'     => 'Acme Ltd',
            'status'          => 'ACTIVE',
            'type'            => 'virtual',
            'currency'        => 'NGN',
            'bvn'             => null,
            'banks'           => [
                ['bankAccountNumber' => '0123456789', 'bankName' => 'Wema Bank', 'bankAccountName' => 'Acme Ltd'],
            ],
            'createdAt'       => '2026-01-01T00:00:00+00:00',
        ], $override);
    }

    private function balanceData(array $override = []): array
    {
        return array_merge([
            'amount'      => '50000.0',
            'currency'    => 'NGN',
            'timeCreated' => '2026-01-01T00:00:00+00:00',
        ], $override);
    }

    public function test_parent_returns_account_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->accountData()]);
        $response = (new AccountResource($client))->parent();

        self::assertInstanceOf(AccountResponse::class, $response);
        self::assertSame('acct_001', $response->accountId);
        self::assertSame('holder_001', $response->accountHolderId);
        self::assertSame('ref_001', $response->accountRef);
        self::assertSame('Acme Ltd', $response->accountName);
        self::assertSame('ACTIVE', $response->status);
        self::assertSame('virtual', $response->type);
        self::assertSame('NGN', $response->currency);
        self::assertNull($response->bvn);
        self::assertCount(1, $response->banks);
        self::assertSame('Wema Bank', $response->banks[0]['bankName']);
        self::assertNotNull($response->createdAt);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/parent', $client->requests[0]['uri']);
    }

    public function test_balance_returns_balance_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->balanceData()]);
        $response = (new AccountResource($client))->balance();

        self::assertInstanceOf(BalanceResponse::class, $response);
        self::assertSame(50000.0, $response->amount);
        self::assertSame('NGN', $response->currency);
        self::assertNotNull($response->createdAt);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/balance', $client->requests[0]['uri']);
    }

    public function test_sub_account_balance_uses_correct_path(): void
    {
        $client   = new FakeHttpClient(['data' => $this->balanceData(['amount' => '5000.0'])]);
        $response = (new AccountResource($client))->subAccountBalance('sub_001');

        self::assertInstanceOf(BalanceResponse::class, $response);
        self::assertSame(5000.0, $response->amount);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/sub_001/balance', $client->requests[0]['uri']);
    }

    public function test_sub_account_returns_account_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->accountData()]);
        $response = (new AccountResource($client))->subAccount('sub_001');

        self::assertInstanceOf(AccountResponse::class, $response);
        self::assertSame('acct_001', $response->accountId);
        self::assertSame('Acme Ltd', $response->accountName);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/sub-account-details', $client->requests[0]['uri']);
        self::assertSame(['accountId' => 'sub_001'], $client->requests[0]['options']['query']);
    }

    public function test_list_returns_paginated_response(): void
    {
        $client   = new FakeHttpClient(['data' => ['results' => [$this->accountData()], 'cursor' => null]]);
        $response = (new AccountResource($client))->list(10);

        self::assertInstanceOf(PaginatedResponse::class, $response);
        self::assertCount(1, $response->results);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/accounts', $client->requests[0]['uri']);
        self::assertSame(['limit' => 10], $client->requests[0]['options']['query']);
    }

    public function test_create_returns_account_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->accountData()]);
        $response = (new AccountResource($client))->create(['email' => 'sub@example.com', 'name' => 'Sub Acct']);

        self::assertInstanceOf(AccountResponse::class, $response);
        self::assertSame('acct_001', $response->accountId);
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/accounts', $client->requests[0]['uri']);
    }

    public function test_terminals_returns_paginated_response(): void
    {
        $terminal = ['terminalId' => 'TID001', 'serialNumber' => 'SN123', 'accountId' => 'acct_001'];
        $client   = new FakeHttpClient(['data' => ['results' => [$terminal], 'cursor' => 'next_cur']]);
        $response = (new AccountResource($client))->terminals();

        self::assertInstanceOf(PaginatedResponse::class, $response);
        self::assertCount(1, $response->results);
        self::assertTrue($response->hasMore());
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/terminals', $client->requests[0]['uri']);
    }

    public function test_sub_account_terminals_uses_correct_path(): void
    {
        $client = new FakeHttpClient(['data' => ['results' => [], 'cursor' => null]]);
        (new AccountResource($client))->subAccountTerminals('sub_001');

        self::assertSame('/v1/accounts/sub_001/terminals', $client->requests[0]['uri']);
    }

    public function test_bvn_is_mapped_when_present(): void
    {
        $client   = new FakeHttpClient(['data' => $this->accountData(['bvn' => '12345678901'])]);
        $response = (new AccountResource($client))->parent();

        self::assertSame('12345678901', $response->bvn);
    }
}
