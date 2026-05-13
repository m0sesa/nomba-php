<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit;

use Nomba\Sdk\NombaClient;
use Nomba\Sdk\Resources\AccountResource;
use Nomba\Sdk\Resources\CheckoutResource;
use Nomba\Sdk\Resources\TransferResource;
use Nomba\Sdk\Resources\VirtualAccountResource;
use Nomba\Sdk\Resources\WebhookManagementResource;
use Nomba\Sdk\Resources\WebhookResource;
use Nomba\Sdk\Tests\Fakes\FakeHttpClient;
use Nomba\Sdk\Webhooks\EventParser;
use Nomba\Sdk\Webhooks\PayloadNormalizer;
use Nomba\Sdk\Webhooks\SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class NombaClientTest extends TestCase
{
    private function client(): NombaClient
    {
        return new NombaClient(
            new FakeHttpClient(),
            new WebhookResource(new SignatureVerifier('secret'), new EventParser(), new PayloadNormalizer()),
        );
    }

    public function test_checkout_returns_checkout_resource(): void
    {
        self::assertInstanceOf(CheckoutResource::class, $this->client()->checkout());
    }

    public function test_virtual_accounts_returns_virtual_account_resource(): void
    {
        self::assertInstanceOf(VirtualAccountResource::class, $this->client()->virtualAccounts());
    }

    public function test_transfers_returns_transfer_resource(): void
    {
        self::assertInstanceOf(TransferResource::class, $this->client()->transfers());
    }

    public function test_webhooks_returns_webhook_resource(): void
    {
        self::assertInstanceOf(WebhookResource::class, $this->client()->webhooks());
    }

    public function test_accounts_returns_account_resource(): void
    {
        self::assertInstanceOf(AccountResource::class, $this->client()->accounts());
    }

    public function test_webhook_management_returns_webhook_management_resource(): void
    {
        self::assertInstanceOf(WebhookManagementResource::class, $this->client()->webhookManagement());
    }

    public function test_resource_instances_are_cached(): void
    {
        $client = $this->client();

        self::assertSame($client->checkout(), $client->checkout());
        self::assertSame($client->virtualAccounts(), $client->virtualAccounts());
        self::assertSame($client->transfers(), $client->transfers());
        self::assertSame($client->accounts(), $client->accounts());
        self::assertSame($client->webhookManagement(), $client->webhookManagement());
        self::assertSame($client->webhooks(), $client->webhooks());
    }
}
