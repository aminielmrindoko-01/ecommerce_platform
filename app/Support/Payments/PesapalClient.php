<?php

namespace App\Support\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Low-level PesaPal API 3.0 HTTP client (sandbox only in Phase 8A–8C).
 *
 * Never logs consumer secrets, bearer tokens, or Authorization headers.
 */
class PesapalClient
{
    /**
     * @return array<string, mixed>
     */
    protected function cfg(): array
    {
        return (array) config('payments.gateways.pesapal', []);
    }

    public function environment(): string
    {
        return strtolower((string) ($this->cfg()['environment'] ?? 'sandbox'));
    }

    public function isSandboxOnlyAllowed(): bool
    {
        return $this->environment() === 'sandbox';
    }

    public function hasCredentials(): bool
    {
        $cfg = $this->cfg();

        return filled($cfg['consumer_key'] ?? null)
            && filled($cfg['consumer_secret'] ?? null);
    }

    public function baseUrl(): string
    {
        if (! $this->isSandboxOnlyAllowed()) {
            throw new RuntimeException('PesaPal production environment is not permitted in Phase 8A/8B/8C.');
        }

        $url = (string) ($this->cfg()['base_urls']['sandbox'] ?? 'https://cybqa.pesapal.com/pesapalv3');

        return rtrim($url, '/');
    }

    /**
     * Safe readiness summary for operators (never includes secrets).
     *
     * @return array{
     *     environment: string,
     *     sandbox_only_allowed: bool,
     *     credentials_configured: bool,
     *     enabled: bool,
     *     live_charging_flag: bool,
     *     configured_ipn_id: bool,
     *     base_url: string|null
     * }
     */
    public function sandboxStatus(): array
    {
        $cfg = $this->cfg();
        $sandboxAllowed = $this->isSandboxOnlyAllowed();

        return [
            'environment' => $this->environment(),
            'sandbox_only_allowed' => $sandboxAllowed,
            'credentials_configured' => $this->hasCredentials(),
            'enabled' => (bool) ($cfg['enabled'] ?? false),
            'live_charging_flag' => (bool) ($cfg['live_charging'] ?? false),
            'configured_ipn_id' => filled($cfg['ipn_id'] ?? null),
            'base_url' => $sandboxAllowed ? $this->baseUrl() : null,
        ];
    }

    public function timeout(): int
    {
        return max(1, (int) ($this->cfg()['timeout'] ?? 15));
    }

    /**
     * @return array{token: string, expiryDate?: string|null}
     */
    public function requestToken(): array
    {
        if (! $this->hasCredentials()) {
            throw new RuntimeException('PesaPal credentials are not configured.');
        }

        if (! $this->isSandboxOnlyAllowed()) {
            throw new RuntimeException('PesaPal production environment is not permitted in Phase 8A/8B/8C.');
        }

        $cacheKey = 'pesapal.sandbox.access_token';

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && filled($cached['token'] ?? null)) {
            return $cached;
        }

        $cfg = $this->cfg();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($this->timeout())
                ->post($this->baseUrl().'/api/Auth/RequestToken', [
                    'consumer_key' => (string) $cfg['consumer_key'],
                    'consumer_secret' => (string) $cfg['consumer_secret'],
                ]);
        } catch (ConnectionException) {
            logger()->warning('pesapal.auth.connection_failed', [
                'environment' => $this->environment(),
            ]);
            throw new RuntimeException('PesaPal authentication timed out or connection failed.');
        }

        if (! $response->successful()) {
            // Never log response bodies (may echo credential-related errors).
            logger()->warning('pesapal.auth.failed', [
                'environment' => $this->environment(),
                'http_status' => $response->status(),
            ]);
            throw new RuntimeException('PesaPal authentication failed.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            logger()->warning('pesapal.auth.malformed', [
                'environment' => $this->environment(),
            ]);
            throw new RuntimeException('PesaPal authentication returned a malformed response.');
        }

        $token = $payload['token'] ?? null;
        if (! is_string($token) || $token === '') {
            logger()->warning('pesapal.auth.missing_token', [
                'environment' => $this->environment(),
            ]);
            throw new RuntimeException('PesaPal authentication response missing token.');
        }

        $result = [
            'token' => $token,
            'expiryDate' => $payload['expiryDate'] ?? null,
        ];

        Cache::put($cacheKey, $result, now()->addMinutes(4));

        logger()->info('pesapal.auth.success', [
            'environment' => $this->environment(),
            'cached_minutes' => 4,
        ]);

        return $result;
    }

    public function registerIpn(string $ipnUrl, string $method = 'POST'): string
    {
        $configured = $this->cfg()['ipn_id'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        $token = $this->requestToken()['token'];
        $cacheKey = 'pesapal.sandbox.ipn_id.'.sha1($ipnUrl.'|'.$method);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout())
                ->post($this->baseUrl().'/api/URLSetup/RegisterIPN', [
                    'url' => $ipnUrl,
                    'ipn_notification_type' => strtoupper($method),
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('PesaPal IPN registration timed out or connection failed.');
        }

        if (! $response->successful()) {
            logger()->warning('pesapal.ipn_register.failed', [
                'http_status' => $response->status(),
            ]);
            throw new RuntimeException('PesaPal IPN registration failed.');
        }

        $payload = $response->json();
        $ipnId = is_array($payload) ? ($payload['ipn_id'] ?? null) : null;

        if (! is_string($ipnId) || $ipnId === '') {
            throw new RuntimeException('PesaPal IPN registration response missing ipn_id.');
        }

        Cache::put($cacheKey, $ipnId, now()->addDay());

        logger()->info('pesapal.ipn_register.success', [
            'ipn_url_host' => parse_url($ipnUrl, PHP_URL_HOST),
        ]);

        return $ipnId;
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @return array<string, mixed>
     */
    public function submitOrderRequest(array $orderPayload): array
    {
        $token = $this->requestToken()['token'];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout())
                ->post($this->baseUrl().'/api/Transactions/SubmitOrderRequest', $orderPayload);
        } catch (ConnectionException) {
            throw new RuntimeException('PesaPal order submission timed out or connection failed.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('PesaPal order submission failed.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('PesaPal order submission returned a malformed response.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransactionStatus(string $orderTrackingId): array
    {
        $token = $this->requestToken()['token'];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout($this->timeout())
                ->get($this->baseUrl().'/api/Transactions/GetTransactionStatus', [
                    'orderTrackingId' => $orderTrackingId,
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('PesaPal transaction status request timed out or connection failed.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('PesaPal transaction status request failed.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('PesaPal transaction status returned a malformed response.');
        }

        return $payload;
    }
}
