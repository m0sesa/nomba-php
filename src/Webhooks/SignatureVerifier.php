<?php

declare(strict_types=1);

namespace Nomba\Sdk\Webhooks;

final class SignatureVerifier
{
    public function __construct(private readonly string $secret) {}

    /**
     * Verify a Nomba HMAC-SHA256 / Base64 signature.
     *
     * $signString is the colon-joined field string built by the caller.
     */
    public function verify(string $signString, string $signature): bool
    {
        if ($this->secret === '') {
            return false;
        }

        $computed = base64_encode(hash_hmac('sha256', $signString, $this->secret, true));

        return hash_equals($computed, $signature);
    }
}
