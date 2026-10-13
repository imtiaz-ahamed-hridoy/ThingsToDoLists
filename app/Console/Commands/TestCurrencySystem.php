<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class TestCurrencySystem extends Command
{
    protected $signature   = 'currency:test {--base-url=http://localhost:8000 : The base URL of your app}';
    protected $description = 'Test the full currency system for all 3 user scenarios';

    private int $passed = 0;
    private int $failed = 0;
    private string $baseUrl;

    public function handle(): int
    {
        $this->baseUrl = rtrim($this->option('base-url'), '/');

        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║         CURRENCY SYSTEM - FULL INTEGRATION TEST              ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // ── Pre-flight ────────────────────────────────────────────────────────────
        $this->step('PRE-FLIGHT: Checking system readiness');
        $this->ensureCurrenciesExist();
        $this->testCurrencyServiceDirectly();

        // ── 3 Scenarios ───────────────────────────────────────────────────────────
        $this->runScenario1Auth();
        $this->runScenario2GuestWithHeader();
        $this->runScenario3NoSelection();

        // ── Summary ───────────────────────────────────────────────────────────────
        $this->newLine();
        $this->line('══════════════════════════════════════════════════════════════');
        $total = $this->passed + $this->failed;
        if ($this->failed === 0) {
            $this->info("✅ ALL {$total} TESTS PASSED — Currency system is fully working!");
        } else {
            $this->error("❌ {$this->failed}/{$total} TESTS FAILED");
        }
        $this->line('══════════════════════════════════════════════════════════════');
        $this->newLine();

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // SCENARIO 1 — Authenticated user selects EUR → saved to DB → auto-applied
    // ══════════════════════════════════════════════════════════════════════════════
    private function runScenario1Auth(): void
    {
        $this->step('SCENARIO 1: Authenticated User Selects Currency (EUR)');
        $this->comment('  Logged-in user picks EUR → saved to DB → auto-applied without header.');

        // 1a. Create / reset a test user
        $user = User::updateOrCreate(
            ['email' => 'currency_test@example.com'],
            [
                'name'               => 'Currency Test User',
                'password'           => Hash::make('Password123!'),
                'role'               => 'user',
                'preferred_currency' => null,
            ]
        );
        $this->assert('Test user created/reset in DB', $user->exists);
        $this->assert('User starts with no preferred currency', empty($user->preferred_currency));

        // 1b. Log in via HTTP (correct endpoint: POST /api/v1/login)
        $loginResp = $this->http()->post("{$this->baseUrl}/api/v1/login", [
            'email'    => 'currency_test@example.com',
            'password' => 'Password123!',
        ]);

        $this->assert('POST /api/v1/login returns 200', $loginResp->successful());
        if (!$loginResp->successful()) {
            $this->warn('  ⚠ Login failed with status: ' . $loginResp->status());
            $this->warn('  ⚠ Response: ' . $loginResp->body());
        }

        // Try multiple token paths since different apps return tokens differently
        $token = data_get($loginResp->json(), 'data.token')
            ?? data_get($loginResp->json(), 'token')
            ?? data_get($loginResp->json(), 'access_token')
            ?? data_get($loginResp->json(), 'data.access_token');

        if (!$token) {
            // Fallback: create token directly in DB (still tests the core logic)
            $this->warn('  ⚠ Could not extract token from login response. Creating token directly.');
            $token = $user->createToken('currency-test')->plainTextToken;
        }

        $this->assert('Got a valid Bearer token', !empty($token));

        // 1c. POST /api/v1/public/currencies/set WITH Bearer token
        $setResp = $this->http()
            ->withToken($token)
            ->post("{$this->baseUrl}/api/v1/currencies/set", ['currency_code' => 'EUR']);

        $this->assert('POST /currencies/set returns 200',   $setResp->successful());
        if (!$setResp->successful()) {
            $this->warn('  ⚠ Set Currency failed with status: ' . $setResp->status());
            $this->warn('  ⚠ Response: ' . $setResp->body());
        }
        $this->assert('Response has success status',        data_get($setResp->json(), 'status') === 200);
        $this->assert('Response currency is EUR',           data_get($setResp->json(), 'data.currency') === 'EUR');
        $this->assert('saved_to_account = true for auth user', data_get($setResp->json(), 'data.saved_to_account') === true);

        // 1d. Verify EUR is actually saved in DB
        $user->refresh();
        $this->assert('EUR saved to users.preferred_currency in DB', $user->preferred_currency === 'EUR');

        // 1e. Call /product/list WITHOUT X-Currency header → middleware must auto-read from DB
        $productsResp = $this->http()
            ->withToken($token)
            ->get("{$this->baseUrl}/api/v1/product/list");

        $this->assert('GET /product/list reachable (no 5xx)', $productsResp->status() < 500);

        if ($productsResp->successful()) {
            // Check first product's currency
            $currency = data_get($productsResp->json(), 'data.data.0.currency')
                ?? data_get($productsResp->json(), 'data.0.currency');

            if ($currency !== null) {
                $this->assert('Product currency is EUR (auto-loaded from DB)', $currency === 'EUR');
            } else {
                $this->warn('  ⚠ No products in DB to verify currency field — skipping product currency check.');
            }
        }

        // 1f. Verify active_currency in /currencies endpoint also reflects EUR
        $currResp = $this->http()
            ->withToken($token)
            ->get("{$this->baseUrl}/api/v1/currencies");
        $this->assert('GET /currencies returns 200', $currResp->successful());
        $this->assert('active_currency is EUR for auth user', data_get($currResp->json(), 'data.active_currency') === 'EUR');

        // 1g. Cleanup
        $user->update(['preferred_currency' => null]);
        $user->tokens()->delete();
        $this->line('│  ✓ Cleanup done');
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // SCENARIO 2 — Guest (no auth) sends X-Currency header / query param
    // ══════════════════════════════════════════════════════════════════════════════
    private function runScenario2GuestWithHeader(): void
    {
        $this->step('SCENARIO 2: Non-Auth Guest Selects Currency (JPY)');
        $this->comment('  No token. POST returns code, frontend stores in sessionStorage, sends as X-Currency header.');

        // 2a. POST /currencies/set WITHOUT a token → must succeed, saved_to_account = false
        $setResp = $this->http()
            ->post("{$this->baseUrl}/api/v1/currencies/set", ['currency_code' => 'JPY']);

        $this->assert('POST /currencies/set returns 200 for guest',   $setResp->successful());
        if (!$setResp->successful()) {
            $this->warn('  ⚠ Guest Set Currency failed with status: ' . $setResp->status());
            $this->warn('  ⚠ Response: ' . $setResp->body());
        }
        $this->assert('Response has success status',                   data_get($setResp->json(), 'status') === 200);
        $this->assert('Response currency is JPY',                      data_get($setResp->json(), 'data.currency') === 'JPY');
        $this->assert('saved_to_account = false for guest',            data_get($setResp->json(), 'data.saved_to_account') === false);

        // 2b. Call /product/list WITH X-Currency: JPY header (frontend re-sends on every call)
        $productsResp = $this->http()
            ->withHeaders(['X-Currency' => 'JPY'])
            ->get("{$this->baseUrl}/api/v1/product/list");

        $this->assert('GET /product/list with X-Currency header reachable', $productsResp->status() < 500);

        if ($productsResp->successful()) {
            $currency = data_get($productsResp->json(), 'data.data.0.currency')
                ?? data_get($productsResp->json(), 'data.0.currency');
            if ($currency !== null) {
                $this->assert('Product currency is JPY via X-Currency header', $currency === 'JPY');
            } else {
                $this->warn('  ⚠ No products — skipping product currency field check.');
            }
        }

        // 2c. Verify active_currency in /currencies also reflects header
        $currResp = $this->http()
            ->withHeaders(['X-Currency' => 'JPY'])
            ->get("{$this->baseUrl}/api/v1/currencies");
        $this->assert('GET /currencies shows active_currency = JPY via header', data_get($currResp->json(), 'data.active_currency') === 'JPY');

        // 2d. Test via ?currency= query param
        $currResp2 = $this->http()->get("{$this->baseUrl}/api/v1/currencies?currency=AED");
        $this->assert('GET /currencies shows active_currency = AED via ?currency= param', data_get($currResp2->json(), 'data.active_currency') === 'AED');

        // 2e. Test invalid currency code is rejected
        $badResp = $this->http()
            ->post("{$this->baseUrl}/api/v1/currencies/set", ['currency_code' => 'FAKE']);
        $this->assert('Invalid currency code is rejected (not 200)', !$badResp->successful());
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // SCENARIO 3 — No auth, no header, no selection → defaults to EUR
    // ══════════════════════════════════════════════════════════════════════════════
    private function runScenario3NoSelection(): void
    {
        $this->step('SCENARIO 3: Fresh Visitor (No Auth, No Header) → Defaults to EUR');
        $this->comment('  No token, no header. Must see EUR.');

        // 3a. /currencies endpoint → active_currency must be EUR
        $currResp = $this->http()->get("{$this->baseUrl}/api/v1/currencies");
        $this->assert('GET /currencies returns 200',                      $currResp->successful());
        $this->assert('active_currency defaults to EUR for fresh visitor', data_get($currResp->json(), 'data.active_currency') === 'EUR');

        // 3b. /product/list with nothing → no 500 error
        $productsResp = $this->http()->get("{$this->baseUrl}/api/v1/product/list");
        $this->assert('GET /product/list reachable for fresh visitor', $productsResp->status() < 500);

        // 3c. Direct CurrencyService unit tests (no HTTP needed)
        $this->comment('  Direct CurrencyService math tests...');
        $service = app(CurrencyService::class);
        $rates   = $service->getExchangeRates();

        $this->assert('Exchange rates loaded',           $rates->count() > 0);
        $this->assert('EUR rate is exactly 1.0',         isset($rates['EUR']) && (float) $rates['EUR'] === 1.0);
        $this->assert('USD rate exists and is > 1',      isset($rates['USD']) && $rates['USD'] > 1.0);
        $this->assert('JPY rate exists and is > 100',    isset($rates['JPY']) && $rates['JPY'] > 100.0);

        $usdAmt = $service->convert(100, 'EUR', 'USD');
        $this->assert('100 EUR → USD is > 100', $usdAmt !== null && $usdAmt > 100);
        $this->line("│     → 100 EUR = {$usdAmt} USD (rate: " . round($rates['USD'] ?? 0, 4) . ')');

        $jpyAmt = $service->convert(100, 'EUR', 'JPY');
        $this->assert('100 EUR → JPY is > 1000',          $jpyAmt !== null && $jpyAmt > 1000);
        $this->line("│     → 100 EUR = {$jpyAmt} JPY (rate: " . round($rates['JPY'] ?? 0, 2) . ')');

        // Same currency — no conversion
        $sameAmt = $service->convert(100, 'EUR', 'EUR');
        $this->assert('100 EUR → EUR returns 100.00',    $sameAmt == 100.0);

        // Null / zero safety
        $this->assert('convert(null) → null',            $service->convert(null, 'EUR', 'USD') === null);
        $this->assert('convert(0) → 0',                  $service->convert(0, 'EUR', 'USD') == 0);
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // PRE-FLIGHT HELPERS
    // ══════════════════════════════════════════════════════════════════════════════
    private function ensureCurrenciesExist(): void
    {
        $count = Currency::count();
        if ($count === 0) {
            $this->warn('  ⚠ Currencies table empty — running CurrencySeeder...');
            $this->call('db:seed', ['--class' => 'CurrencySeeder', '--force' => true]);
            Cache::forget('currency_exchange_rates');
            Cache::forget('active_currencies');
            $count = Currency::count();
        }
        $this->assert("Currencies table populated ({$count} rows)", $count > 0);
    }

    private function testCurrencyServiceDirectly(): void
    {
        $service = app(CurrencyService::class);
        $rates   = $service->getExchangeRates();
        $list    = $service->getActiveCurrencies();
        $this->assert('CurrencyService::getExchangeRates() works', $rates->count() > 0);
        $this->assert('CurrencyService::getActiveCurrencies() works', $list->count() > 0);
        $this->assert('EUR base rate exists', $rates->has('EUR'));
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // UTILITY
    // ══════════════════════════════════════════════════════════════════════════════

    /**
     * Returns an HTTP client with SSL verification disabled for local .test domains.
     */
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withOptions([
            'verify'  => false,   // allow self-signed certs on .test / localhost
            'timeout' => 15,
        ])->acceptJson();
    }

    private function step(string $title): void
    {
        $this->newLine();
        $this->line("┌─ <fg=cyan>{$title}</fg=cyan>");
        $this->line('│');
    }

    private function assert(string $label, bool $condition): void
    {
        if ($condition) {
            $this->line("│  <fg=green>✓ PASS</fg=green>  {$label}");
            $this->passed++;
        } else {
            $this->line("│  <fg=red>✗ FAIL</fg=red>  {$label}");
            $this->failed++;
        }
    }
}
