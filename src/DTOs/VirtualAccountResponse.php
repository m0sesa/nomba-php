<?php

declare(strict_types=1);

namespace Nomba\Sdk\DTOs;

use DateTimeImmutable;

final readonly class VirtualAccountResponse
{
    public function __construct(
        public string            $accountRef,
        public string            $accountHolderId,
        public string            $accountName,
        public string            $bankName,
        public string            $bankAccountNumber,
        public string            $bankAccountName,
        public string            $currency,
        public bool              $expired,
        public ?string           $bvn,
        public ?string           $callbackUrl,
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
            accountRef:        (string) ($data['accountRef'] ?? ''),
            accountHolderId:   (string) ($data['accountHolderId'] ?? ''),
            accountName:       (string) ($data['accountName'] ?? ''),
            bankName:          (string) ($data['bankName'] ?? ''),
            bankAccountNumber: (string) ($data['bankAccountNumber'] ?? ''),
            bankAccountName:   (string) ($data['bankAccountName'] ?? ''),
            currency:          (string) ($data['currency'] ?? 'NGN'),
            expired:           (bool)   ($data['expired'] ?? false),
            bvn:               isset($data['bvn']) ? (string) $data['bvn'] : null,
            callbackUrl:       isset($data['callbackUrl']) ? (string) $data['callbackUrl'] : null,
            createdAt:         $createdAt,
        );
    }
}
