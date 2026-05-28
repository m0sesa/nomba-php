<?php

declare(strict_types=1);

namespace Nomba\Sdk\DTOs;

final readonly class CheckoutResponse
{
    public function __construct(
        public string  $orderReference,
        public string  $checkoutLink,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderReference: (string) ($data['orderReference'] ?? ''),
            checkoutLink:   (string) ($data['checkoutLink']   ?? ''),
        );
    }
}
