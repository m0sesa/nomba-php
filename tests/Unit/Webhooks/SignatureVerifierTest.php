<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Webhooks;

use Nomba\Sdk\Webhooks\SignatureVerifier;
use PHPUnit\Framework\TestCase;

final class SignatureVerifierTest extends TestCase
{
    private const SECRET = 'test-secret';

    private function sign(string $signString): string
    {
        return base64_encode(hash_hmac('sha256', $signString, self::SECRET, true));
    }

    public function test_verify_returns_true_for_valid_signature(): void
    {
        $signString = 'payment_success:req_001:user_001:wallet_001:txn_001:vact_transfer:2026-01-01T00:00:00Z::2026-01-01T00:00:00Z';

        self::assertTrue((new SignatureVerifier(self::SECRET))->verify($signString, $this->sign($signString)));
    }

    public function test_verify_returns_false_for_tampered_sign_string(): void
    {
        $original  = 'payment_success:req_001:user_001:wallet_001:txn_001:vact_transfer:2026-01-01T00:00:00Z::2026-01-01T00:00:00Z';
        $tampered  = 'payout_success:req_001:user_001:wallet_001:txn_001:vact_transfer:2026-01-01T00:00:00Z::2026-01-01T00:00:00Z';

        self::assertFalse((new SignatureVerifier(self::SECRET))->verify($tampered, $this->sign($original)));
    }

    public function test_verify_returns_false_when_secret_is_empty(): void
    {
        self::assertFalse((new SignatureVerifier(''))->verify('any-sign-string', 'any-signature'));
    }
}
