<?php

declare(strict_types=1);

namespace Nomba\Sdk\Webhooks;

use DateTimeImmutable;
use Nomba\Sdk\DTOs\WebhookEvent;
use Nomba\Sdk\Enums\WebhookEventType;
use Nomba\Sdk\Exceptions\WebhookException;

final class EventParser
{
    /**
     * @param array<string, mixed> $payload
     */
    public function parse(array $payload, string $timestamp = ''): WebhookEvent
    {
        $id = (string) ($payload['requestId'] ?? '');

        if ($id === '') {
            throw new WebhookException('Webhook payload missing required field: requestId.');
        }

        $type = WebhookEventType::fromValue((string) ($payload['event_type'] ?? ''));

        $inner       = (array) ($payload['data']                      ?? []);
        $transaction = (array) ($inner['transaction']                 ?? []);

        $occurredAt = match (true) {
            $timestamp !== ''             => new DateTimeImmutable($timestamp),
            isset($transaction['time'])   => new DateTimeImmutable((string) $transaction['time']),
            default                       => new DateTimeImmutable(),
        };

        return new WebhookEvent(
            id:         $id,
            type:       $type,
            payload:    $inner,
            occurredAt: $occurredAt,
        );
    }
}
