<?php

namespace Finller\Forex\Integrations\ExchangeRateApi;

use Finller\Forex\ForexClient;
use Finller\Forex\Integrations\ExchangeRateApi\Requests\LatestRequest;
use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Cacheable;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\RateLimitPlugin\Contracts\RateLimitStore;
use Saloon\RateLimitPlugin\Limit;
use Saloon\RateLimitPlugin\Stores\LaravelCacheStore;
use Saloon\RateLimitPlugin\Traits\HasRateLimits;

/**
 * Free exchange rate values updated once a day
 *
 * @see https://www.exchangerate-api.com/docs/free
 */
class ExchangeRateApiConnector extends Connector implements Cacheable, ForexClient
{
    use HasCaching;
    use HasRateLimits;

    public function __construct()
    {
        $this->cachingEnabled = config('forex.cache.enabled', true);
        $this->useRateLimitPlugin(config('forex.rate_limit.enabled', false));
    }

    public function resolveBaseUrl(): string
    {
        return 'https://open.er-api.com/v6/';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(Cache::store(config('forex.cache.driver', 'file')));
    }

    public function cacheExpiryInSeconds(): int
    {
        return config('forex.cache.expiry_seconds', 86_400);
    }

    protected function resolveRateLimitStore(): RateLimitStore
    {
        return new LaravelCacheStore(Cache::store(config('forex.rate_limit.driver', 'array')));
    }

    protected function resolveLimits(): array
    {
        return [
            Limit::allow(1)->everySeconds(config('forex.rate_limit.every_seconds')),
        ];
    }

    public function latest(string $currency): Response
    {
        return $this->send(new LatestRequest($currency));
    }

    public function rates(string $currency): array
    {
        return $this->latest($currency)->json('rates', []);
    }
}
