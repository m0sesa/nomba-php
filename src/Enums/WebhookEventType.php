<?php

declare(strict_types=1);

namespace Nomba\Sdk\Enums;

enum WebhookEventType: string
{
    case PaymentSuccess   = 'payment_success';
    case PaymentFailed    = 'payment_failed';
    case PaymentReversal  = 'payment_reversal';
    case PayoutSuccess    = 'payout_success';
    case PayoutFailed     = 'payout_failed';
    case PayoutRefund     = 'payout_refund';
    case Unknown          = 'unknown';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }
}
