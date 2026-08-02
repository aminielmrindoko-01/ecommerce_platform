<?php

namespace App\Support\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Low-level PesaPal API 3.0 HTTP client (sandbox only in Phase 8A).
 *
 * Never logs consumer secrets or bearer tokens.
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
            throw new RuntimeException('PesaPal production environment is not permitted in Phase 8A.');
        }

        $url = (string) ($this->cfg()['base_urls']['sandbox'] ?? 'https://cybqa.pesapal.com/pesapalv3');

        return rtrim($url, '/');
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
            throw new RuntimeException('PesaPal production environment is not permitted in Phase 8A.');
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
            throw new RuntimeException('PesaPal authentication timed out or connection failed.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('PesaPal authentication failed.');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('PesaPal authentication returned a malformed response.');
        }

        $token = $payload['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('PesaPal authentication response missing token.');
        }

        $result = [
            'token' => $token,
            'expiryDate' => $payload['expiryDate'] ?? null,
        ];

        Cache::put($cacheKey, $result, now()->addMinutes(4));

        return $result;
    }

    public function registerIpn(string $ipnUrl, string $method = 'POST'): string
    {
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
            throw new RuntimeException('PesaPal IPN registration failed.');
        }

        $payload = $response->json();
        $ipnId = is_array($payload) ? ($payload['ipn_id'] ?? null) : null;

        if (! is_string($ipnId) || $ipnId === '') {
            throw new RuntimeException('PesaPal IPN registration response missing ipn_id.');
        }

        Cache::put($cacheKey, $ipnId, now()->addDay());

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
