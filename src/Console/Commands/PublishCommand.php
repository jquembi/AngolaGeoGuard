<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;

/**
 * Atalho para publicar apenas os artefactos do angola-geoguard
 * (config, migrations, traducoes), sem ter de escrever o nome
 * completo do provider em `vendor:publish`.
 */
final class PublishCommand extends Command
{
    protected $signature = 'geoguard:publish {--force : Sobrescreve ficheiros ja publicados}';

    protected $description = 'Publica os ficheiros de configuracao, migrations e traducoes do angola-geoguard.';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--provider' => 'JoseQuembi\\AngolaGeoGuard\\AngolaGeoGuardServiceProvider',
            '--force' => (bool) $this->option('force'),
        ]);

        return self::SUCCESS;
    }
}
