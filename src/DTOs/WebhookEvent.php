<?php

declare(strict_types=1);

namespace Nomba\Sdk\DTOs;

use DateTimeImmutable;
use Nomba\Sdk\Enums\WebhookEventType;

final readonly class WebhookEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string           $id,
        public WebhookEventType $type,
        public array            $payload,
        public DateTimeImmutable $occurredAt,
    ) {}
}
