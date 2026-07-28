<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JoseQuembi\AngolaGeoGuard\Models\Province;

/**
 * Pre-aquece o cache com os dados administrativos e geometrias das
 * provincias, reduzindo a latencia da primeira decisao apos deploy.
 * Ver secao 23.
 */
final class WarmCacheCommand extends Command
{
    protected $signature = 'geoguard:cache';

    protected $description = 'Pre-aquece o cache com os dados de provincias e configuracao ativa.';

    public function handle(): int
    {
        $store = config('angola-geoguard.cache.store');
        $prefix = config('angola-geoguard.cache.prefix', 'geoguard');
        $ttl = (int) config('angola-geoguard.location.cache_ttl', 3600);

        $cache = $store ? Cache::store($store) : Cache::store();

        $provinces = Province::query()->where('is_active', true)->get();

        $cache->put("{$prefix}:provinces:all", $provinces->toArray(), $ttl);

        foreach ($provinces as $province) {
            $cache->put("{$prefix}:province:{$province->slug}", $province->toArray(), $ttl);
        }

        $this->components->info(sprintf('Cache pre-aquecido com %d provincia(s) (TTL: %ds).', $provinces->count(), $ttl));

        return self::SUCCESS;
    }
}
