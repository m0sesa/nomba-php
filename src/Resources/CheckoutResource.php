<?php

declare(strict_types=1);

namespace Nomba\Sdk\Resources;

use Nomba\Sdk\Contracts\HttpClientInterface;
use Nomba\Sdk\DTOs\CheckoutResponse;
use Nomba\Sdk\DTOs\CheckoutTransactionResponse;

final class CheckoutResource
{
    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * Create an online checkout order and return a payment link.
     *
     * POST /v1/checkout/order  →  body: { order: {...}, tokenizeCard?: bool }
     *
     * @param array{
     *     amount: int|float,
     *     currency: string,
     *     callbackUrl: string,
     *     customerEmail: string,
     *     orderReference?: string,
     *     customerId?: string,
     *     allowedPaymentMethods?: string[],
     * } $payload
     * @param bool|null $tokenizeCard  When true, Nomba saves the card for future charges.
     */
    public function create(array $payload, ?bool $tokenizeCard = null): CheckoutResponse
    {
        $body = ['order' => $payload];

        if ($tokenizeCard !== null) {
            $body['tokenizeCard'] = $tokenizeCard;
        }

        $response = $this->httpClient->request('POST', '/v1/checkout/order', ['payload' => $body]);

        return CheckoutResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Fetch a previously created checkout order by its reference.
     * Response fields are under data.order — checkoutLink is not available on fetch.
     */
    public function find(string $orderReference): CheckoutResponse
    {
        $response = $this->httpClient->request('GET', "/v1/checkout/order/{$orderReference}");

        return CheckoutResponse::fromArray((array) ($response['data']['order'] ?? []));
    }

    /**
     * Fetch a checkout transaction with full details (order, payment, card, transfer).
     *
     * @param string $id      The order ID or order reference value.
     * @param string $idType  'ORDER_REFERENCE' (default) or 'ORDER_ID'.
     */
    public function fetchTransaction(string $id, string $idType = 'ORDER_REFERENCE'): CheckoutTransactionResponse
    {
        $response = $this->httpClient->request('GET', '/v1/checkout/transaction', [
            'query' => ['idType' => $idType, 'id' => $id],
        ]);

        return CheckoutTransactionResponse::fromArray((array) ($response['data'] ?? []));
    }

    /**
     * Cancel an open checkout order. Returns true when the API confirms cancellation.
     */
    public function cancel(string $orderReference): bool
    {
        $response = $this->httpClient->request('POST', "/v1/checkout/order/{$orderReference}/cancel");

        return ($response['code'] ?? '') === '00';
    }

    /**
     * Refund a completed checkout order, in full or partially.
     *
     * @return array<string, mixed>  Raw refund record returned by the API.
     */
    public function refund(string $orderReference, int|float $amount, string $narration = ''): array
    {
        $payload = ['amount' => $amount];

        if ($narration !== '') {
            $payload['narration'] = $narration;
        }

        $response = $this->httpClient->request('POST', "/v1/checkout/order/{$orderReference}/refund", [
            'payload' => $payload,
        ]);

        return (array) ($response['data'] ?? []);
    }
}
