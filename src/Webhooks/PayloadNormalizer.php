<?php

declare(strict_types=1);

namespace Nomba\Sdk\Webhooks;

use Nomba\Sdk\Exceptions\WebhookException;

final class PayloadNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(string|array $payload): array
    {
        if (\is_array($payload)) {
            return $payload;
        }

        $decoded = json_decode($payload, true);

        if (!\is_array($decoded)) {
            throw new WebhookException('Invalid webhook payload: could not decode JSON.');
        }

        return $decoded;
    }
}
