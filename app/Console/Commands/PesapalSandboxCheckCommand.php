<?php

namespace App\Console\Commands;

use App\Support\Payments\PesapalClient;
use App\Support\Payments\PesapalGateway;
use Illuminate\Console\Command;
use Throwable;

/**
 * Operator health check for PesaPal sandbox readiness (Phase 8C).
 *
 * Never prints secrets, tokens, or Authorization material.
 */
class PesapalSandboxCheckCommand extends Command
{
    protected $signature = 'payments:pesapal-sandbox-check
                            {--auth : Attempt a real sandbox RequestToken call}';

    protected $description = 'Report PesaPal sandbox configuration readiness (no secrets printed)';

    public function handle(PesapalClient $client, PesapalGateway $gateway): int
    {
        $status = $client->sandboxStatus();

        $this->info('PesaPal sandbox status (safe fields only)');
        $this->table(
            ['Field', 'Value'],
            [
                ['environment', $status['environment']],
                ['sandbox_only_allowed', $status['sandbox_only_allowed'] ? 'yes' : 'no'],
                ['enabled', $status['enabled'] ? 'yes' : 'no'],
                ['live_charging_flag', $status['live_charging_flag'] ? 'yes' : 'no'],
                ['credentials_configured', $status['credentials_configured'] ? 'yes' : 'no'],
                ['configured_ipn_id', $status['configured_ipn_id'] ? 'yes' : 'no'],
                ['supports_live_charging', $gateway->supportsLiveCharging() ? 'yes' : 'no'],
                ['base_url', $status['base_url'] ?? 'n/a'],
                ['allowed_redirect_hosts', implode(', ', (array) config('payments.gateways.pesapal.allowed_redirect_hosts', []))],
                ['default_gateway', (string) config('payments.default', 'stub')],
            ]
        );

        if (! $status['sandbox_only_allowed']) {
            $this->error('FAIL CLOSED: PESAPAL_ENV is not sandbox. Production processing is not permitted.');

            return self::FAILURE;
        }

        if (! $status['credentials_configured']) {
            $this->warn('Credentials missing — application remains in Coming Soon / stub mode. No payment will be attempted.');

            return self::SUCCESS;
        }

        if (! $this->option('auth')) {
            $this->comment('Credentials present. Re-run with --auth to exercise sandbox RequestToken (never logs the token).');

            return self::SUCCESS;
        }

        try {
            $client->requestToken();
            $this->info('AUTH: PASS (sandbox token obtained; token value not printed)');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('AUTH: FAIL — '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
