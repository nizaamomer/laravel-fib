<?php

declare(strict_types=1);

namespace Nizaamomer\LaravelFib\Services;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Http;
use Nizaamomer\LaravelFib\Contracts\FibAuthServiceContract;
use Nizaamomer\LaravelFib\Exceptions\FibAccountException;
use Nizaamomer\LaravelFib\Exceptions\FibAuthenticationException;

final class FibAuthService implements FibAuthServiceContract
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    public function token(string $account): string
    {
        $cacheKey = $this->cacheKey($account);

        /** @var string|null $cached */
        $cached = $this->cacheStore()->get($cacheKey);

        return $cached ?? $this->requestToken($account);
    }

    public function refreshToken(string $account): string
    {
        $this->cacheStore()->forget($this->cacheKey($account));

        return $this->token($account);
    }

    private function requestToken(string $account): string
    {
        $config = $this->accountConfig($account);

        $response = Http::asForm()
            ->timeout((int) config('fib.http.timeout', 15))
            ->post("{$config['base_url']}/auth/realms/fib-online-shop/protocol/openid-connect/token", [
                'grant_type' => $config['grant_type'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
            ]);

        if ($response->failed()) {
            throw FibAuthenticationException::tokenRequestFailed($response->status(), $response->body());
        }

        $token = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 60);
        $safetyMargin = (int) config('fib.token_cache.safety_margin_seconds', 5);
        $ttl = max($expiresIn - $safetyMargin, 5);

        $this->cacheStore()->put($this->cacheKey($account), $token, now()->addSeconds($ttl));

        return $token;
    }

    /**
     * @return array{base_url: string, client_id: string, client_secret: string, grant_type: string}
     */
    private function accountConfig(string $account): array
    {
        $config = config("fib.accounts.{$account}");

        if (! is_array($config) || empty($config['client_id']) || empty($config['client_secret']) || empty($config['base_url'])) {
            throw FibAccountException::unknownAccount($account);
        }

        return [
            'base_url' => (string) $config['base_url'],
            'client_id' => (string) $config['client_id'],
            'client_secret' => (string) $config['client_secret'],
            'grant_type' => (string) ($config['grant_type'] ?? 'client_credentials'),
        ];
    }

    private function cacheKey(string $account): string
    {
        return "fib.token.{$account}";
    }

    private function cacheStore(): Repository
    {
        return $this->cache->store(config('fib.token_cache.store'));
    }
}
