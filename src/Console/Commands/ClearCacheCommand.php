<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class ClearCacheCommand extends Command
{
    protected $signature = 'geoguard:clear-cache';

    protected $description = 'Limpa todas as entradas de cache do angola-geoguard.';

    public function handle(): int
    {
        $store = config('angola-geoguard.cache.store');
        $prefix = config('angola-geoguard.cache.prefix', 'geoguard');

        $cache = is_string($store) && $store !== '' ? Cache::store($store) : Cache::store();

        // A limpeza granular por prefixo depende do driver (Redis
        // suporta SCAN por padrao; outros drivers exigem flush total
        // do store dedicado). Aqui usamos o metodo mais seguro e
        // universalmente suportado: flush do store configurado para
        // o pacote, que se recomenda ser dedicado em producao.
        $cache->getStore()->flush();

        $this->components->info(sprintf('Cache do angola-geoguard (prefixo "%s") limpo.', $prefix));

        return self::SUCCESS;
    }
}
