<?php

declare(strict_types=1);

namespace Nomba\Sdk\Resources;

use Nomba\Sdk\DTOs\WebhookEvent;
use Nomba\Sdk\Webhooks\EventParser;
use Nomba\Sdk\Webhooks\PayloadNormalizer;
use Nomba\Sdk\Webhooks\SignatureVerifier;

final class WebhookResource
{
    public function __construct(
        private readonly SignatureVerifier  $verifier,
        private readonly EventParser        $parser,
        private readonly PayloadNormalizer  $normalizer,
    ) {}

    /**
     * Verify the Nomba HMAC-SHA256 / Base64 signature.
     *
     * @param string|array<string, mixed> $payload   Raw JSON body or already-decoded array
     * @param string                      $timestamp  Value of the `nomba-timestamp` header
     * @param string                      $signature  Value of the `nomba-signature` header
     */
    public function verify(string|array $payload, string $timestamp, string $signature): bool
    {
        $data = $this->normalizer->normalize($payload);

        $inner       = (array) ($data['data']                  ?? []);
        $merchant    = (array) ($inner['merchant']             ?? []);
        $transaction = (array) ($inner['transaction']          ?? []);

        $signString = implode(':', [
            (string) ($data['event_type']                   ?? ''),
            (string) ($data['requestId']                    ?? ''),
            (string) ($merchant['userId']                   ?? ''),
            (string) ($merchant['walletId']                 ?? ''),
            (string) ($transaction['transactionId']         ?? ''),
            (string) ($transaction['type']                  ?? ''),
            (string) ($transaction['time']                  ?? ''),
            (string) ($transaction['responseCode']          ?? ''),
            $timestamp,
        ]);

        return $this->verifier->verify($signString, $signature);
    }

    /**
     * @param string|array<string, mixed> $payload
     */
    public function parse(string|array $payload, string $timestamp = ''): WebhookEvent
    {
        return $this->parser->parse(
            $this->normalizer->normalize($payload),
            $timestamp,
        );
    }
}
