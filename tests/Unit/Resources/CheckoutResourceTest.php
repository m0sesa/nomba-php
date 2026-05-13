<?php

declare(strict_types=1);

namespace Nomba\Sdk\Tests\Unit\Resources;

use Nomba\Sdk\DTOs\CheckoutResponse;
use Nomba\Sdk\DTOs\CheckoutTransactionResponse;
use Nomba\Sdk\Resources\CheckoutResource;
use Nomba\Sdk\Tests\Fakes\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class CheckoutResourceTest extends TestCase
{
    private function orderData(array $override = []): array
    {
        return array_merge([
            'orderReference' => 'ref_001',
            'checkoutLink'   => 'https://checkout.nomba.com/ref_001',
        ], $override);
    }

    public function test_create_returns_checkout_response(): void
    {
        $client   = new FakeHttpClient(['data' => $this->orderData()]);
        $response = (new CheckoutResource($client))->create(['amount' => 5000, 'currency' => 'NGN', 'callbackUrl' => 'https://example.com/cb', 'customerEmail' => 'a@b.com']);

        self::assertInstanceOf(CheckoutResponse::class, $response);
        self::assertSame('ref_001', $response->orderReference);
        self::assertSame('https://checkout.nomba.com/ref_001', $response->checkoutLink);
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/checkout/order', $client->requests[0]['uri']);
        self::assertArrayHasKey('order', $client->requests[0]['options']['payload']);
        self::assertArrayNotHasKey('tokenizeCard', $client->requests[0]['options']['payload']);
    }

    public function test_create_sends_tokenize_card_when_true(): void
    {
        $client = new FakeHttpClient(['data' => $this->orderData()]);
        (new CheckoutResource($client))->create(['amount' => 5000, 'currency' => 'NGN', 'callbackUrl' => 'https://example.com/cb', 'customerEmail' => 'a@b.com'], true);

        self::assertTrue($client->requests[0]['options']['payload']['tokenizeCard']);
    }

    public function test_create_omits_tokenize_card_when_null(): void
    {
        $client = new FakeHttpClient(['data' => $this->orderData()]);
        (new CheckoutResource($client))->create(['amount' => 5000, 'currency' => 'NGN', 'callbackUrl' => 'https://example.com/cb', 'customerEmail' => 'a@b.com'], null);

        self::assertArrayNotHasKey('tokenizeCard', $client->requests[0]['options']['payload']);
    }

    public function test_find_returns_checkout_response(): void
    {
        $client   = new FakeHttpClient(['data' => ['order' => $this->orderData()]]);
        $response = (new CheckoutResource($client))->find('ref_001');

        self::assertInstanceOf(CheckoutResponse::class, $response);
        self::assertSame('ref_001', $response->orderReference);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/checkout/order/ref_001', $client->requests[0]['uri']);
    }

    public function test_fetch_transaction_returns_transaction_response(): void
    {
        $client = new FakeHttpClient([
            'data' => [
                'success'            => true,
                'message'            => 'Transaction found.',
                'order'              => $this->orderData(),
                'transactionDetails' => ['statusCode' => '00', 'paymentReference' => 'pay_001'],
                'transferDetails'    => [],
                'cardDetails'        => [],
            ],
        ]);

        $response = (new CheckoutResource($client))->fetchTransaction('ref_001');

        self::assertInstanceOf(CheckoutTransactionResponse::class, $response);
        self::assertTrue($response->success);
        self::assertSame('ref_001', $response->order['orderReference']);
        self::assertSame('00', $response->transactionDetails['statusCode']);
        self::assertSame('GET', $client->requests[0]['method']);
        self::assertSame('/v1/checkout/transaction', $client->requests[0]['uri']);
        self::assertSame(['idType' => 'ORDER_REFERENCE', 'id' => 'ref_001'], $client->requests[0]['options']['query']);
    }

    public function test_fetch_transaction_accepts_order_id_type(): void
    {
        $client = new FakeHttpClient(['data' => ['success' => true, 'message' => '', 'order' => [], 'transactionDetails' => [], 'transferDetails' => [], 'cardDetails' => []]]);

        (new CheckoutResource($client))->fetchTransaction('ord_001', 'ORDER_ID');

        self::assertSame(['idType' => 'ORDER_ID', 'id' => 'ord_001'], $client->requests[0]['options']['query']);
    }

    public function test_cancel_returns_true_on_success(): void
    {
        $client = new FakeHttpClient(['code' => '00', 'description' => 'Order cancelled.']);
        $result = (new CheckoutResource($client))->cancel('ref_001');

        self::assertTrue($result);
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/checkout/order/ref_001/cancel', $client->requests[0]['uri']);
    }

    public function test_cancel_returns_false_on_non_zero_code(): void
    {
        $client = new FakeHttpClient(['code' => '06', 'description' => 'Order cannot be cancelled.']);

        self::assertFalse((new CheckoutResource($client))->cancel('ref_001'));
    }

    public function test_refund_posts_amount_and_narration(): void
    {
        $client = new FakeHttpClient(['code' => '00', 'data' => ['refundId' => 'rfnd_001', 'amount' => 2500]]);
        $result = (new CheckoutResource($client))->refund('ref_001', 2500, 'Partial refund');

        self::assertSame('rfnd_001', $result['refundId']);
        self::assertSame('POST', $client->requests[0]['method']);
        self::assertSame('/v1/checkout/order/ref_001/refund', $client->requests[0]['uri']);
        self::assertSame(['amount' => 2500, 'narration' => 'Partial refund'], $client->requests[0]['options']['payload']);
    }

    public function test_refund_omits_narration_when_empty(): void
    {
        $client = new FakeHttpClient(['code' => '00', 'data' => []]);
        (new CheckoutResource($client))->refund('ref_001', 5000);

        self::assertArrayNotHasKey('narration', $client->requests[0]['options']['payload']);
    }
}
