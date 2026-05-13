<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit;

use Nomba\Sdk\Enums\Environment;
use Nomba\Sdk\Factory;
use Nomba\Sdk\NombaClient;
use Nomba\Sdk\Resources\WebhookResource;
use Nomba\Sdk\Support\NombaConfig;
use Nomba\Sdk\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class FactoryTest extends TestCase
{
    private function config(): NombaConfig
    {
        return new NombaConfig(
            clientId:     'cid',
            clientSecret: 'secret',
            accountId:    'acct',
            environment:  Environment::Sandbox,
            webhookSecret: 'whsec',
        );
    }

    public function test_with_client_returns_nomba_client(): void
    {
        $client = Factory::withClient(new FakeHttpClient(), $this->config());

        self::assertInstanceOf(NombaClient::class, $client);
    }

    public function test_with_client_wires_webhook_resource(): void
    {
        $client = Factory::withClient(new FakeHttpClient(), $this->config());

        self::assertInstanceOf(WebhookResource::class, $client->webhooks());
    }

    public function test_with_client_webhook_verifies_correct_secret(): void
    {
        $client    = Factory::withClient(new FakeHttpClient(), $this->config());
        $timestamp = '2026-01-01T00:00:00Z';
        $payload   = [
            'requestId'  => 'req_001',
            'event_type' => 'payment_success',
            'data'       => [
                'merchant'    => ['userId' => 'u1', 'walletId' => 'w1'],
                'transaction' => ['transactionId' => 'txn_001', 'type' => 'vact_transfer', 'time' => $timestamp, 'responseCode' => ''],
            ],
        ];
        $signStr   = 'payment_success:req_001:u1:w1:txn_001:vact_transfer:' . $timestamp . '::' . $timestamp;
        $sig       = base64_encode(hash_hmac('sha256', $signStr, 'whsec', true));

        self::assertTrue($client->webhooks()->verify($payload, $timestamp, $sig));
    }
}
