<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Webhooks;

use Nomba\Sdk\DTOs\WebhookEvent;
use Nomba\Sdk\Enums\WebhookEventType;
use Nomba\Sdk\Exceptions\WebhookException;
use Nomba\Sdk\Webhooks\EventParser;
use PHPUnit\Framework\TestCase;

final class EventParserTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'requestId'  => 'req_001',
            'event_type' => 'payment_success',
            'data'       => [
                'merchant'    => ['userId' => 'user_001', 'walletId' => 'wallet_001'],
                'transaction' => ['transactionId' => 'txn_001', 'type' => 'vact_transfer', 'time' => '2026-01-01T00:00:00Z', 'responseCode' => ''],
                'customer'    => [],
            ],
        ], $override);
    }

    public function test_parse_returns_webhook_event(): void
    {
        $event = (new EventParser())->parse($this->payload(), '2026-01-01T00:00:00Z');

        self::assertInstanceOf(WebhookEvent::class, $event);
        self::assertSame('req_001', $event->id);
        self::assertSame(WebhookEventType::PaymentSuccess, $event->type);
        self::assertIsArray($event->payload);
    }

    public function test_parse_maps_unknown_event_type(): void
    {
        $event = (new EventParser())->parse($this->payload(['event_type' => 'some_unknown_event']));

        self::assertSame(WebhookEventType::Unknown, $event->type);
    }

    public function test_parse_throws_when_request_id_is_missing(): void
    {
        $this->expectException(WebhookException::class);

        $payload = $this->payload();
        unset($payload['requestId']);
        (new EventParser())->parse($payload);
    }

    public function test_parse_uses_timestamp_parameter_for_occurred_at(): void
    {
        $event = (new EventParser())->parse($this->payload(), '2026-06-01T12:00:00+00:00');

        self::assertSame('2026-06-01', $event->occurredAt->format('Y-m-d'));
    }

    public function test_parse_falls_back_to_transaction_time(): void
    {
        $event = (new EventParser())->parse($this->payload());

        self::assertSame('2026-01-01', $event->occurredAt->format('Y-m-d'));
    }
}
