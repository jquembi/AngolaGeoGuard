<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;

/**
 * Comando de conveniencia para a configuracao inicial: publica
 * ficheiros, corre migrations e semeia Angola numa unica chamada.
 */
final class InstallCommand extends Command
{
    protected $signature = 'geoguard:install {--force : Sobrescreve ficheiros ja publicados}';

    protected $description = 'Publica configuracao/migrations, corre as migrations e semeia as 21 provincias.';

    public function handle(): int
    {
        $this->components->info('A instalar Angola GeoGuard...');

        $this->call('vendor:publish', [
            '--provider' => 'JoseQuembi\\AngolaGeoGuard\\AngolaGeoGuardServiceProvider',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->call('migrate');

        $this->call('geoguard:seed-angola');

        $this->components->info('Instalacao concluida. Corre "php artisan geoguard:diagnose" para verificar a configuracao.');

        return self::SUCCESS;
    }
}
