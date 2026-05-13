<?php

declare(strict_types=1);

namespace Nomba\Sdk;

use Nomba\Sdk\Contracts\HttpClientInterface;
use Nomba\Sdk\Contracts\NombaClientInterface;
use Nomba\Sdk\Resources\AccountResource;
use Nomba\Sdk\Resources\CheckoutResource;
use Nomba\Sdk\Resources\TransferResource;
use Nomba\Sdk\Resources\VirtualAccountResource;
use Nomba\Sdk\Resources\WebhookManagementResource;
use Nomba\Sdk\Resources\WebhookResource;

final class NombaClient implements NombaClientInterface
{
    private ?CheckoutResource          $checkout          = null;
    private ?VirtualAccountResource    $virtualAccounts   = null;
    private ?TransferResource          $transfers         = null;
    private ?AccountResource           $accounts          = null;
    private ?WebhookManagementResource $webhookManagement = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly WebhookResource     $webhookResource,
    ) {}

    public function checkout(): CheckoutResource
    {
        return $this->checkout ??= new CheckoutResource($this->httpClient);
    }

    public function virtualAccounts(): VirtualAccountResource
    {
        return $this->virtualAccounts ??= new VirtualAccountResource($this->httpClient);
    }

    public function transfers(): TransferResource
    {
        return $this->transfers ??= new TransferResource($this->httpClient);
    }

    public function accounts(): AccountResource
    {
        return $this->accounts ??= new AccountResource($this->httpClient);
    }

    public function webhookManagement(): WebhookManagementResource
    {
        return $this->webhookManagement ??= new WebhookManagementResource($this->httpClient);
    }

    public function webhooks(): WebhookResource
    {
        return $this->webhookResource;
    }
}
