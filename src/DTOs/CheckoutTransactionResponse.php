<?php

declare(strict_types=1);

namespace Nomba\Sdk\DTOs;

final readonly class CheckoutTransactionResponse
{
    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed> $transactionDetails
     * @param array<string, mixed> $transferDetails
     * @param array<string, mixed> $cardDetails
     */
    public function __construct(
        public bool   $success,
        public string $message,
        public array  $order,
        public array  $transactionDetails,
        public array  $transferDetails,
        public array  $cardDetails,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success:            (bool)  ($data['success']            ?? false),
            message:            (string) ($data['message']           ?? ''),
            order:              (array) ($data['order']              ?? []),
            transactionDetails: (array) ($data['transactionDetails'] ?? []),
            transferDetails:    (array) ($data['transferDetails']    ?? []),
            cardDetails:        (array) ($data['cardDetails']        ?? []),
        );
    }
}
