<?php

declare(strict_types=1);

namespace Nomba\Sdk\DTOs;

use DateTimeImmutable;

final readonly class AccountResponse
{
    /**
     * @param array<int, array{bankAccountNumber: string, bankName: string, bankAccountName: string}> $banks
     */
    public function __construct(
        public string             $accountId,
        public string             $accountHolderId,
        public string             $accountRef,
        public string             $accountName,
        public string             $status,
        public string             $type,
        public string             $currency,
        public ?string            $bvn,
        public array              $banks,
        public ?DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $createdAt = isset($data['createdAt'])
            ? new DateTimeImmutable((string) $data['createdAt'])
            : null;

        return new self(
            accountId:       (string) ($data['accountId']       ?? ''),
            accountHolderId: (string) ($data['accountHolderId'] ?? ''),
            accountRef:      (string) ($data['accountRef']      ?? ''),
            accountName:     (string) ($data['accountName']     ?? ''),
            status:          (string) ($data['status']          ?? ''),
            type:            (string) ($data['type']            ?? ''),
            currency:        (string) ($data['currency']        ?? 'NGN'),
            bvn:             isset($data['bvn']) ? (string) $data['bvn'] : null,
            banks:           (array)  ($data['banks']           ?? []),
            createdAt:       $createdAt,
        );
    }
}
