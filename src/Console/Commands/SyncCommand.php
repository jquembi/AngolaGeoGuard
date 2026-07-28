<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use JoseQuembi\AngolaGeoGuard\Models\GeoDataSource;

/**
 * Lista as fontes de dados configuradas e o estado da sua ultima
 * atualizacao conhecida. A sincronizacao ativa (descarregar e
 * comparar hash com uma fonte remota) e um ponto de extensao: a
 * aplicacao host pode agendar `geoguard:import` apos detetar uma
 * nova versao publicada pela fonte oficial. Ver secao 5/22.
 */
final class SyncCommand extends Command
{
    protected $signature = 'geoguard:sync';

    protected $description = 'Lista as fontes de dados geograficos configuradas e o estado da sua validacao.';

    public function handle(): int
    {
        $sources = GeoDataSource::query()->get();

        if ($sources->isEmpty()) {
            $this->components->warn('Nenhuma fonte de dados registada ainda. Use "geoguard:import" para criar a primeira.');

            return self::SUCCESS;
        }

        $this->table(
            ['Fonte', 'Estado', 'Ultima atualizacao', 'Licenca'],
            $sources->map(fn (GeoDataSource $s) => [
                $s->name,
                $s->validation_status,
                $s->last_updated_at?->diffForHumans() ?? 'nunca',
                $s->license ?? '-',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
