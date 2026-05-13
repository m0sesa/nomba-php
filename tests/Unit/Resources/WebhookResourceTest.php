<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Resources;

use Nomba\Sdk\DTOs\WebhookEvent;
use Nomba\Sdk\Enums\WebhookEventType;
use Nomba\Sdk\Exceptions\WebhookException;
use Nomba\Sdk\Resources\WebhookResource;
use Nomba\Sdk\Webhooks\EventParser;
use Nomba\Sdk\Webhooks\PayloadNormalizer;
use Nomba\Sdk\Webhooks\SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookResourceTest extends TestCase
{
    private const SECRET = 'test-secret';

    private function resource(): WebhookResource
    {
        return new WebhookResource(
            new SignatureVerifier(self::SECRET),
            new EventParser(),
            new PayloadNormalizer(),
        );
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'requestId'  => 'req_001',
            'event_type' => 'payment_success',
            'data'       => [
                'merchant'    => ['userId' => 'user_001', 'walletId' => 'wallet_001'],
                'transaction' => ['transactionId' => 'txn_001', 'type' => 'vact_transfer', 'time' => '2026-01-01T00:00:00Z', 'responseCode' => ''],
                'customer'    => [],
            ],
        ];
    }

    private function sign(string $signString): string
    {
        return base64_encode(hash_hmac('sha256', $signString, self::SECRET, true));
    }

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $timestamp  = '2026-01-01T00:00:00Z';
        $signString = 'payment_success:req_001:user_001:wallet_001:txn_001:vact_transfer:2026-01-01T00:00:00Z::' . $timestamp;

        self::assertTrue($this->resource()->verify($this->payload(), $timestamp, $this->sign($signString)));
    }

    public function test_verify_returns_true_for_json_string_payload(): void
    {
        $timestamp  = '2026-01-01T00:00:00Z';
        $signString = 'payment_success:req_001:user_001:wallet_001:txn_001:vact_transfer:2026-01-01T00:00:00Z::' . $timestamp;

        self::assertTrue($this->resource()->verify((string) json_encode($this->payload()), $timestamp, $this->sign($signString)));
    }

    public function test_verify_returns_false_for_invalid_signature(): void
    {
        self::assertFalse($this->resource()->verify($this->payload(), '2026-01-01T00:00:00Z', 'bad-signature'));
    }

    public function test_parse_accepts_json_string_and_returns_event(): void
    {
        $event = $this->resource()->parse((string) json_encode($this->payload()));

        self::assertInstanceOf(WebhookEvent::class, $event);
        self::assertSame('req_001', $event->id);
        self::assertSame(WebhookEventType::PaymentSuccess, $event->type);
        self::assertIsArray($event->payload);
    }

    public function test_parse_accepts_array_payload(): void
    {
        $data              = $this->payload();
        $data['event_type'] = 'payout_success';
        $data['requestId']  = 'req_002';

        $event = $this->resource()->parse($data);

        self::assertSame('req_002', $event->id);
        self::assertSame(WebhookEventType::PayoutSuccess, $event->type);
    }

    public function test_parse_passes_timestamp_to_parser(): void
    {
        $event = $this->resource()->parse($this->payload(), '2026-06-01T12:00:00+00:00');

        self::assertSame('2026-06-01', $event->occurredAt->format('Y-m-d'));
    }

    public function test_parse_throws_for_invalid_json(): void
    {
        $this->expectException(WebhookException::class);

        $this->resource()->parse('not-valid-json');
    }
}
