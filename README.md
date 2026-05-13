# nomba-php

Framework-agnostic PHP SDK for the [Nomba API](https://nomba.com).

## Requirements

- PHP 8.2+
- [Guzzle](https://docs.guzzlephp.org) 7.x (installed automatically)

## Installation

```bash
composer require nomba/nomba-php
```

## Quick start

```php
use Nomba\Sdk\Factory;
use Nomba\Sdk\Enums\Environment;

$nomba = Factory::make(
    clientId:     'your-client-id',
    clientSecret: 'your-client-secret',
    accountId:    'your-account-id',
    environment:  Environment::Production,  // default: Sandbox
    webhookSecret: 'your-webhook-secret',   // optional
);
```

Tokens are fetched and refreshed automatically. You never call an auth endpoint directly.

## Resources

### Checkout

```php
// Create a payment order
$order = $nomba->checkout()->create([
    'amount'         => 5000,
    'currency'       => 'NGN',
    'callbackUrl'    => 'https://yourapp.com/callback',
    'customerEmail'  => 'user@example.com',
    'orderReference' => 'order_abc',             // optional
]);

echo $order->checkoutLink;   // redirect the customer here
echo $order->orderReference;

// Fetch an existing order by reference
$order = $nomba->checkout()->find('order_abc');
```

### Virtual Accounts

```php
// Create a virtual account
$account = $nomba->virtualAccounts()->create([
    'accountRef'  => 'ref_john_doe_001',   // required, unique reference
    'accountName' => 'John Doe',
    'bvn'         => '12345678901',        // optional
]);

echo $account->bankAccountNumber;
echo $account->bankName;

// Fetch by reference or account number
$account = $nomba->virtualAccounts()->find('acct_ref_xxx');

// List virtual accounts — limit/cursor are query params, other fields filter the body
$page = $nomba->virtualAccounts()->list(['limit' => 20, 'accountName' => 'John']);
```

### Transfers

```php
// Bank transfer
$transfer = $nomba->transfers()->bankTransfer([
    'amount'        => 10000,
    'accountNumber' => '0123456789',
    'accountName'   => 'John Doe',
    'bankCode'      => '058',
    'merchantTxRef' => 'invoice_001',  // unique per transaction
    'senderName'    => 'Acme Ltd',
    'narration'     => 'Invoice payment',  // optional
]);

echo $transfer->id;
echo $transfer->fee;

// Wallet transfer
$transfer = $nomba->transfers()->walletTransfer([
    'amount'            => 5000,
    'receiverAccountId' => 'uuid-of-receiver-account',
    'merchantTxRef'     => 'wallet_tx_001',
    'narration'         => 'Bonus payment',  // optional
]);

// Fetch supported bank codes
$banks = $nomba->transfers()->banks();
// [['code' => '058', 'name' => 'Guaranty Trust Bank'], ...]
```

### Webhooks

Verify the signature and parse the event in two steps:

```php
$raw       = file_get_contents('php://input');
$signature = $_SERVER['HTTP_NOMBA_SIGNATURE'] ?? '';
$timestamp = $_SERVER['HTTP_NOMBA_TIMESTAMP'] ?? '';

if (!$nomba->webhooks()->verify($raw, $timestamp, $signature)) {
    http_response_code(401);
    exit;
}

$event = $nomba->webhooks()->parse($raw, $timestamp);

echo $event->id;          // requestId from Nomba
echo $event->type->value; // payment_success
print_r($event->payload); // the data object from Nomba
```

Supported event types (`WebhookEventType` enum):

| Case | Value |
|------|-------|
| `PaymentSuccess` | `payment_success` |
| `PaymentFailed` | `payment_failed` |
| `PaymentReversal` | `payment_reversal` |
| `PayoutSuccess` | `payout_success` |
| `PayoutFailed` | `payout_failed` |
| `PayoutRefund` | `payout_refund` |
| `Unknown` | `unknown` |

## Error handling

All exceptions extend `Nomba\Sdk\Exceptions\NombaException`.

```php
use Nomba\Sdk\Exceptions\AuthenticationException;
use Nomba\Sdk\Exceptions\RateLimitException;
use Nomba\Sdk\Exceptions\ValidationException;
use Nomba\Sdk\Exceptions\ApiException;
use Nomba\Sdk\Exceptions\NetworkException;

try {
    $order = $nomba->checkout()->create([...]);
} catch (RateLimitException $e) {
    $retryIn = $e->retryAfter();  // seconds derived from X-Rate-Limit-Window, or null
    $limit   = $e->limit();       // e.g. "40"
    $window  = $e->window();      // e.g. "1s"
} catch (ValidationException $e) {
    $errors = $e->errors();
} catch (AuthenticationException $e) {
    // 401 / 403
} catch (ApiException $e) {
    $status  = $e->getCode();
    $context = $e->context(); // raw response body
} catch (NetworkException $e) {
    // connection failure / timeout
}
```

## Token caching (PSR-16)

By default tokens are cached in-memory, which works for queue workers, Octane, and CLI. For short-lived PHP-FPM processes, pass a PSR-16 cache so tokens survive across requests:

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

$psr16 = new Psr16Cache(new RedisAdapter(Redis::create('redis://localhost')));

$nomba = Factory::make(
    clientId:     '...',
    clientSecret: '...',
    accountId:    '...',
    cache:        $psr16,
);
```

## Testing

Inject `FakeHttpClient` via `Factory::withClient()` to unit-test your own code without hitting the network:

```php
use Nomba\Sdk\Factory;
use Nomba\Sdk\Support\NombaConfig;
use Nomba\Sdk\Tests\Fakes\FakeHttpClient;

$fake = new FakeHttpClient([
    'data' => ['orderReference' => 'ref_001', 'checkoutLink' => 'https://...'],
]);

$nomba = Factory::withClient($fake, new NombaConfig(
    clientId:     'test',
    clientSecret: 'test',
    accountId:    'test',
));

$order = $nomba->checkout()->create([...]);
```

## PSR-18 interop

Bring your own PSR-18 HTTP client instead of Guzzle:

```php
use Nomba\Sdk\Http\PsrHttpClientAdapter;

$adapter = new PsrHttpClientAdapter($psrClient, $requestFactory, $streamFactory);
$nomba   = Factory::withClient($adapter, $config);
```

## Environments

| Enum case | Base URL |
|-----------|----------|
| `Environment::Sandbox` | `https://sandbox.nomba.com/v1` |
| `Environment::Production` | `https://api.nomba.com/v1` |

## License

MIT
