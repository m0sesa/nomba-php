<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Resources;

use Nomba\Sdk\Resources\WebhookManagementResource;
use Nomba\Sdk\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class WebhookManagementResourceTest extends TestCase
{
    public function test_event_logs_posts_filters(): void
    {
        $client = new FakeHttpClient(['code' => '200', 'data' => ['list' => [], 'pageToken' => null]]);
        $result = (new WebhookManagementResource($client))->eventLogs(['eventType' => 'payment_success', 'limit' => 10]);

        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/webhooks/event-logs', $client->requests[0]['uri']);
        self::assertSame(['eventType' => 'payment_success', 'limit' => 10], $client->requests[0]['options']['payload']);
    }

    public function test_events_posts_filters(): void
    {
        $client = new FakeHttpClient(['code' => '200', 'data' => ['list' => []]]);
        (new WebhookManagementResource($client))->events(['limit' => 5]);

        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/webhooks/events', $client->requests[0]['uri']);
    }

    public function test_repush_returns_true_on_success(): void
    {
        $client = new FakeHttpClient(['code' => '200', 'description' => 'Webhooks message re-pushed successfully']);
        $result = (new WebhookManagementResource($client))->repush('hook_req_001');

        self::assertTrue($result);
        self::assertSame('/v1/webhooks/re-push', $client->requests[0]['uri']);
        self::assertSame(['hooksRequestId' => 'hook_req_001'], $client->requests[0]['options']['payload']);
    }

    public function test_repush_returns_false_on_failure(): void
    {
        $client = new FakeHttpClient(['code' => '01']);

        self::assertFalse((new WebhookManagementResource($client))->repush('hook_req_001'));
    }

    public function test_bulk_repush_sends_array_of_ids(): void
    {
        $client = new FakeHttpClient(['code' => '200']);
        $result = (new WebhookManagementResource($client))->bulkRepush(['id_1', 'id_2']);

        self::assertTrue($result);
        self::assertSame('/v1/webhooks/bulk-re-push', $client->requests[0]['uri']);
        self::assertSame(['hooksRequestIds' => ['id_1', 'id_2']], $client->requests[0]['options']['payload']);
    }

    public function test_replay_sends_date_range_and_filter(): void
    {
        $client = new FakeHttpClient(['code' => '200']);
        $result = (new WebhookManagementResource($client))->replay(
            '2026-01-01T00:00:00Z',
            '2026-01-31T23:59:59Z',
            ['statuses' => ['FAILED'], 'eventTypes' => ['payment_success']],
        );

        self::assertTrue($result);
        self::assertSame('/v1/webhooks/replay', $client->requests[0]['uri']);
        self::assertSame('2026-01-01T00:00:00Z', $client->requests[0]['options']['payload']['startDate']);
        self::assertSame(['FAILED'], $client->requests[0]['options']['payload']['filter']['statuses']);
    }

    public function test_replay_omits_filter_key_when_empty(): void
    {
        $client = new FakeHttpClient(['code' => '200']);
        (new WebhookManagementResource($client))->replay('2026-01-01T00:00:00Z', '2026-01-31T23:59:59Z');

        self::assertArrayNotHasKey('filter', $client->requests[0]['options']['payload']);
    }
}
