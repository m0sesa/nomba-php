<?php

declare(strict_types=1);

namespace Nomba\Sdk\Resources;

use Nomba\Sdk\Contracts\HttpClientInterface;

/**
 * Manages Nomba webhook configuration and replay via the API.
 *
 * This resource is distinct from WebhookResource, which handles
 * incoming webhook signature verification and event parsing.
 */
final class WebhookManagementResource
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * Fetch delivery logs for webhook events.
     *
     * @param array{
     *     coreUserId?: string,
     *     limit?: int,
     *     eventType?: string,
     *     startDateTime?: string,
     *     endDateTime?: string,
     * } $filters
     * @return array<string, mixed>
     */
    public function eventLogs(array $filters = []): array
    {
        $response = $this->httpClient->request('POST', '/v1/webhooks/event-logs', [
            'payload' => $filters,
        ]);

        return (array) ($response['data'] ?? []);
    }

    /**
     * List registered webhook event subscriptions.
     *
     * @param array{
     *     coreUserId?: string,
     *     limit?: int,
     * } $filters
     * @return array<string, mixed>
     */
    public function events(array $filters = []): array
    {
        $response = $this->httpClient->request('POST', '/v1/webhooks/events', [
            'payload' => $filters,
        ]);

        return (array) ($response['data'] ?? []);
    }

    /**
     * Re-push a single previously failed webhook delivery.
     */
    public function repush(string $hookRequestId): bool
    {
        $response = $this->httpClient->request('POST', '/v1/webhooks/re-push', [
            'payload' => ['hooksRequestId' => $hookRequestId],
        ]);

        return ($response['code'] ?? '') === '200';
    }

    /**
     * Re-push multiple previously failed webhook deliveries in bulk.
     *
     * @param string[] $hookRequestIds
     */
    public function bulkRepush(array $hookRequestIds): bool
    {
        $response = $this->httpClient->request('POST', '/v1/webhooks/bulk-re-push', [
            'payload' => ['hooksRequestIds' => $hookRequestIds],
        ]);

        return ($response['code'] ?? '') === '200';
    }

    /**
     * Replay all webhooks within a date range, optionally filtered by status and event type.
     *
     * @param array{
     *     statuses?: string[],
     *     eventTypes?: string[],
     * } $filter
     */
    public function replay(string $startDate, string $endDate, array $filter = []): bool
    {
        $payload = ['startDate' => $startDate, 'endDate' => $endDate];

        if ($filter !== []) {
            $payload['filter'] = $filter;
        }

        $response = $this->httpClient->request('POST', '/v1/webhooks/replay', [
            'payload' => $payload,
        ]);

        return ($response['code'] ?? '') === '200';
    }
}
