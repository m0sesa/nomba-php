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

        $signString = implode(':', [
            $data['event_type']                              ?? '',
            $data['requestId']                               ?? '',
            $data['data']['merchant']['userId']              ?? '',
            $data['data']['merchant']['walletId']            ?? '',
            $data['data']['transaction']['transactionId']    ?? '',
            $data['data']['transaction']['type']             ?? '',
            $data['data']['transaction']['time']             ?? '',
            $data['data']['transaction']['responseCode']     ?? '',
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
