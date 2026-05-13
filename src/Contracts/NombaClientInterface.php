<?php

declare(strict_types=1);

namespace Nomba\Sdk\Contracts;

use Nomba\Sdk\Resources\AccountResource;
use Nomba\Sdk\Resources\CheckoutResource;
use Nomba\Sdk\Resources\TransferResource;
use Nomba\Sdk\Resources\VirtualAccountResource;
use Nomba\Sdk\Resources\WebhookManagementResource;
use Nomba\Sdk\Resources\WebhookResource;

interface NombaClientInterface
{
    public function checkout(): CheckoutResource;

    public function virtualAccounts(): VirtualAccountResource;

    public function transfers(): TransferResource;

    public function accounts(): AccountResource;

    public function webhookManagement(): WebhookManagementResource;

    public function webhooks(): WebhookResource;
}
