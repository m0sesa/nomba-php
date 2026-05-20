#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Nomba Smoke Test
 *
 * Usage:
 *   # Sandbox (default)
 *   php smoke-test.php
 *
 *   # Production
 *   NOMBA_ENVIRONMENT=production \
 *   NOMBA_CLIENT_ID=xxx \
 *   NOMBA_CLIENT_SECRET=yyy \
 *   NOMBA_ACCOUNT_ID=zzz \
 *   php smoke-test.php
 */

foreach ([__DIR__ . '/vendor/autoload.php', __DIR__ . '/../vendor/autoload.php'] as $autoload) {
    if (file_exists($autoload)) { require $autoload; break; }
}

use Nomba\Sdk\Enums\Environment;
use Nomba\Sdk\Factory;

$clientId     = (string) (getenv('NOMBA_CLIENT_ID')     ?: '');
$clientSecret = (string) (getenv('NOMBA_CLIENT_SECRET') ?: '');
$accountId    = (string) (getenv('NOMBA_ACCOUNT_ID')    ?: '');

$environment = Environment::tryFrom(
    strtolower((string) (getenv('NOMBA_ENVIRONMENT') ?: 'sandbox'))
);

if ($environment === null) {
    fwrite(
        STDERR,
        "Error: NOMBA_ENVIRONMENT must be 'sandbox' or 'production'.\n"
    );

    exit(1);
}

// IF not needed for Sandbox but required for production, so we enforce it here to ensure the test environment is correctly configured.
if (Environment::Production === $environment && ($clientId === '' || $clientSecret === '' || $accountId === '')) {
    fwrite(STDERR, "Error: NOMBA_CLIENT_ID, NOMBA_CLIENT_SECRET, and NOMBA_ACCOUNT_ID must be set.\n");
    exit(1);
}

$nomba = Factory::make(
    clientId:     $clientId,
    clientSecret: $clientSecret,
    accountId:    $accountId,
    environment:  $environment,
    timeout:      15.0,
);

// ---------------------------------------------------------------------------

$passed = 0;
$failed = 0;

function run(string $label, callable $test): void
{
    global $passed, $failed;
    $start = microtime(true);
    try {
        $detail = $test();
        $ms     = (int) round((microtime(true) - $start) * 1000);
        echo sprintf("  PASS  %-52s %dms%s\n", $label, $ms, $detail !== '' ? "  {$detail}" : '');
        $passed++;
    } catch (\Throwable $e) {
        $ms = (int) round((microtime(true) - $start) * 1000);
        echo sprintf("  FAIL  %-52s %dms  %s: %s\n", $label, $ms, get_class($e), $e->getMessage());
        $failed++;
    }
}

// ---------------------------------------------------------------------------

echo "\nNomba Sandbox Smoke Test\n";
echo str_repeat('-', 72) . "\n";

// --- Accounts ---------------------------------------------------------------
echo "\nAccounts\n";

run('parent account', function () use ($nomba): string {
    $a = $nomba->accounts()->parent();
    return "accountId={$a->accountId}  status={$a->status}";
});

run('parent balance', function () use ($nomba): string {
    $b = $nomba->accounts()->balance();
    return "amount={$b->amount}  currency={$b->currency}";
});

run('list sub-accounts (limit 5)', function () use ($nomba): string {
    $list = $nomba->accounts()->list(5);
    return count($list->results) . ' result(s)  hasMore=' . ($list->hasMore() ? 'true' : 'false');
});

// --- Virtual Accounts -------------------------------------------------------
echo "\nVirtual Accounts\n";

$vaRef = 'smoke-' . time();

run('create virtual account', function () use ($nomba, $vaRef): string {
    $va = $nomba->virtualAccounts()->create([
        'accountRef'  => $vaRef,
        'accountName' => 'Smoke Test',
    ]);
    return "accountRef={$va->accountRef}  expired=" . ($va->expired ? 'true' : 'false');
});

run('fetch virtual account by ref', function () use ($nomba, $vaRef): string {
    $va = $nomba->virtualAccounts()->find($vaRef);
    return "accountName={$va->accountName}  bank={$va->bankName}";
});

run('list virtual accounts (limit 5)', function () use ($nomba): string {
    $list = $nomba->virtualAccounts()->list(['limit' => 5]);
    return count($list->results) . ' result(s)';
});

// --- Transfers --------------------------------------------------------------
echo "\nTransfers\n";

run('fetch bank list', function () use ($nomba): string {
    $banks = $nomba->transfers()->banks();
    return count($banks) . ' bank(s)';
});

run('lookup bank account', function () use ($nomba): string {
    $result = $nomba->transfers()->lookupBankAccount([
        'accountNumber' => '0000000000',
        'bankCode'      => '058',
    ]);
    return 'accountName=' . ($result['accountName'] ?? 'n/a');
});

// --- Checkout ---------------------------------------------------------------
echo "\nCheckout\n";

run('create checkout order', function () use ($nomba): string {
    $order = $nomba->checkout()->create([
        'amount'        => 100,
        'currency'      => 'NGN',
        'callbackUrl'   => 'https://example.com/callback',
        'customerEmail' => 'test@example.com',
    ]);
    return "orderReference={$order->orderReference}";
});

// ---------------------------------------------------------------------------

$total = $passed + $failed;
echo "\n" . str_repeat('-', 72) . "\n";
echo "Result: {$passed}/{$total} passed" . ($failed > 0 ? "  ({$failed} failed)" : '') . "\n\n";

exit($failed > 0 ? 1 : 0);
