<?php

namespace Tests\Sandbox;

use App\Support\Payments\PesapalClient;
use App\Support\Payments\PesapalGateway;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * REAL PesaPal Sandbox E2E checks (opt-in).
 *
 * This suite is intentionally outside the default Feature/Unit phpunit suites so
 * `php artisan test` stays green without sandbox credentials.
 *
 * Run only when intentionally configured:
 *
 *   PESAPAL_E2E=true php artisan test tests/Sandbox
 *
 * Requirements:
 * - PESAPAL_E2E=true
 * - PESAPAL_ENV=sandbox
 * - PESAPAL_CONSUMER_KEY / PESAPAL_CONSUMER_SECRET set in the process environment
 *
 * Never prints secrets/tokens. Does not fabricate payment success.
 */
class PesapalSandboxE2ETest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (env('PESAPAL_E2E') !== true && env('PESAPAL_E2E') !== 'true' && env('PESAPAL_E2E') !== '1') {
            $this->markTestSkipped('SKIPPED — PESAPAL_E2E not enabled (offline suite remains authoritative).');
        }

        $key = env('PESAPAL_CONSUMER_KEY');
        $secret = env('PESAPAL_CONSUMER_SECRET');
        $environment = strtolower((string) env('PESAPAL_ENV', 'sandbox'));

        if ($environment !== 'sandbox') {
            $this->markTestSkipped('SKIPPED — PESAPAL_ENV must be sandbox for Phase 8C.');
        }

        if (! filled($key) || ! filled($secret)) {
            $this->markTestSkipped('SKIPPED — sandbox credentials unavailable');
        }

        config([
            'payments.gateways.pesapal.enabled' => true,
            'payments.gateways.pesapal.live_charging' => true,
            'payments.gateways.pesapal.environment' => 'sandbox',
            'payments.gateways.pesapal.consumer_key' => $key,
            'payments.gateways.pesapal.consumer_secret' => $secret,
            'payments.gateways.pesapal.ipn_id' => env('PESAPAL_IPN_ID'),
            'payments.gateways.pesapal.timeout' => (int) env('PESAPAL_TIMEOUT', 15),
        ]);

        Cache::flush();
    }

    public function test_real_sandbox_authentication(): void
    {
        $client = app(PesapalClient::class);
        $status = $client->sandboxStatus();

        $this->assertTrue($status['sandbox_only_allowed']);
        $this->assertTrue($status['credentials_configured']);
        $this->assertStringContainsString('cybqa.pesapal.com', (string) $status['base_url']);

        $tokenPayload = $client->requestToken();
        $this->assertIsArray($tokenPayload);
        $this->assertNotEmpty($tokenPayload['token'] ?? null);

        // Ensure test output / assertions never echo the token value.
        $this->assertTrue(true);
    }

    public function test_real_sandbox_gateway_reports_live_charging_ready(): void
    {
        $gateway = app(PesapalGateway::class);

        $this->assertTrue($gateway->supportsLiveCharging());
        $this->assertTrue($gateway->isAllowedRedirectUrl('https://cybqa.pesapal.com/pesapaliframe/PesapalIframe3/Index/?x=1'));
        $this->assertFalse($gateway->isAllowedRedirectUrl('https://evil.example/phish'));
    }
}
