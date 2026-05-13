<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Resources;

use Nomba\Sdk\DTOs\PaginatedResponse;
use Nomba\Sdk\DTOs\VirtualAccountResponse;
use Nomba\Sdk\Resources\VirtualAccountResource;
use Nomba\Sdk\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class VirtualAccountResourceTest extends TestCase
{
    private function data(array $override = []): array
    {
        return array_merge([
            'accountRef'        => 'acct_ref_001',
            'accountHolderId'   => 'holder_001',
            'accountName'       => 'John Doe',
            'bankName'          => 'Wema Bank',
            'bankAccountNumber' => '0123456789',
            'bankAccountName'   => 'John Doe',
            'currency'          => 'NGN',
            'expired'           => false,
            'bvn'               => null,
            'callbackUrl'       => null,
            'createdAt'         => '2026-01-01T00:00:00+00:00',
        ], $override);
    }

    public function test_find_returns_virtual_account_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->data()]);
        $response = (new VirtualAccountResource($client))->find('acct_ref_001');

        self::assertInstanceOf(VirtualAccountResponse::class, $response);
        self::assertSame('acct_ref_001', $response->accountRef);
        self::assertSame('Wema Bank', $response->bankName);
        self::assertFalse($response->expired);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/virtual/acct_ref_001', $client->requests[0]['uri']);
    }

    public function test_create_returns_virtual_account_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->data()]);
        $response = (new VirtualAccountResource($client))->create(['accountName' => 'John Doe']);

        self::assertInstanceOf(VirtualAccountResponse::class, $response);
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/virtual', $client->requests[0]['uri']);
    }

    public function test_list_returns_paginated_response(): void
    {
        $client = new FakeHttpClient([
            'data' => [
                'results' => [$this->data()],
                'cursor'  => null,
            ],
        ]);

        $response = (new VirtualAccountResource($client))->list(['limit' => 10, 'accountName' => 'John']);

        self::assertInstanceOf(PaginatedResponse::class, $response);
        self::assertCount(1, $response->results);
        self::assertFalse($response->hasMore());
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/virtual/list', $client->requests[0]['uri']);
        // limit/cursor go to query string, not body
        self::assertSame(['limit' => 10], $client->requests[0]['options']['query']);
        self::assertSame(['accountName' => 'John'], $client->requests[0]['options']['payload']);
    }

    public function test_update_returns_updated_virtual_account(): void
    {
        $client   = new FakeHttpClient(['data' => $this->data(['accountName' => 'Jane Doe'])]);
        $response = (new VirtualAccountResource($client))->update('acct_ref_001', ['accountName' => 'Jane Doe']);

        self::assertInstanceOf(VirtualAccountResponse::class, $response);
        self::assertSame('PUT', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/virtual/acct_ref_001', $client->requests[0]['uri']);
        self::assertSame(['accountName' => 'Jane Doe'], $client->requests[0]['options']['payload']);
    }

    public function test_expire_returns_true_on_success(): void
    {
        $client = new FakeHttpClient(['code' => '00', 'description' => 'Success']);
        $result = (new VirtualAccountResource($client))->expire('acct_ref_001');

        self::assertTrue($result);
        self::assertSame('PUT', $client->requests[0]['method']);
        self::assertSame('/v1/accounts/virtual/acct_ref_001/expire', $client->requests[0]['uri']);
    }

    public function test_expire_returns_false_on_non_zero_code(): void
    {
        $client = new FakeHttpClient(['code' => '01', 'description' => 'Error']);

        self::assertFalse((new VirtualAccountResource($client))->expire('acct_ref_001'));
    }
}
