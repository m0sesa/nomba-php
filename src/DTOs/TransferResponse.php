<?php

declare(strict_types=1);

namespace Nomba\Sdk\DTOs;

use DateTimeImmutable;
use Nomba\Sdk\Enums\TransferStatus;

final readonly class TransferResponse
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string             $id,
        public TransferStatus     $status,
        public string             $type,
        public float              $amount,
        public float              $fee,
        public ?DateTimeImmutable $timeCreated,
        public array              $meta = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $timeCreated = isset($data['timeCreated'])
            ? new DateTimeImmutable((string) $data['timeCreated'])
            : null;

        return new self(
            id:          (string) ($data['id']     ?? ''),
            status:      TransferStatus::fromValue((string) ($data['status'] ?? '')),
            type:        (string) ($data['type']   ?? ''),
            amount:      (float)  ($data['amount'] ?? 0.0),
            fee:         (float)  ($data['fee']    ?? 0.0),
            timeCreated: $timeCreated,
            meta:        (array)  ($data['meta']   ?? []),
        );
    }
}
