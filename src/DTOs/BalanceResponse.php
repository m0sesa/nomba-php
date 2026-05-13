<?php

declare(strict_types=1);

namespace Nomba\Sdk\DTOs;

use DateTimeImmutable;

final readonly class BalanceResponse
{
    public function __construct(
        public float              $amount,
        public string             $currency,
        public ?DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $createdAt = isset($data['timeCreated'])
            ? new DateTimeImmutable((string) $data['timeCreated'])
            : null;

        return new self(
            amount:    (float)  ($data['amount']   ?? 0.0),
            currency:  (string) ($data['currency'] ?? 'NGN'),
            createdAt: $createdAt,
        );
    }
}
