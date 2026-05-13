<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Webhooks;

use Nomba\Sdk\Exceptions\WebhookException;
use Nomba\Sdk\Webhooks\PayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class PayloadNormalizerTest extends TestCase
{
    public function test_normalize_returns_array_unchanged(): void
    {
        $input = ['id' => 'evt_001', 'type' => 'payment.completed'];

        self::assertSame($input, (new PayloadNormalizer())->normalize($input));
    }

    public function test_normalize_decodes_json_string(): void
    {
        $payload = '{"id":"evt_001","type":"payment.completed"}';
        $result  = (new PayloadNormalizer())->normalize($payload);

        self::assertSame('evt_001', $result['id']);
        self::assertSame('payment.completed', $result['type']);
    }

    public function test_normalize_throws_for_invalid_json(): void
    {
        $this->expectException(WebhookException::class);

        (new PayloadNormalizer())->normalize('not-valid-json');
    }
}
