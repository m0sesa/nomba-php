<?php

declare(strict_types=1);

namespace Nomba\Sdk\DTOs;

final readonly class PaginatedResponse
{
    /**
     * @param array<int, mixed> $results
     */
    public function __construct(
        public array   $results,
        public ?string $cursor,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $cursor = isset($data['cursor']) && $data['cursor'] !== ''
            ? (string) $data['cursor']
            : null;

        return new self(
            results: (array) ($data['results'] ?? []),
            cursor:  $cursor,
        );
    }

    public function hasMore(): bool
    {
        return $this->cursor !== null;
    }
}
