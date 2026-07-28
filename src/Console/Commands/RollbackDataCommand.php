<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use JoseQuembi\AngolaGeoGuard\Events\GeoDataRolledBack;
use JoseQuembi\AngolaGeoGuard\Models\GeoDataVersion;

/**
 * Reverte o estado "published" para uma versao anterior de dados
 * territoriais. Nao apaga o historico — apenas marca a versao atual
 * como "rolled_back" e a versao alvo volta a "published". A geometria
 * em si (armazenada em geo_provinces) deve ser re-importada a partir
 * do payload da versao alvo pela aplicacao host, ja que o pacote nao
 * guarda uma copia completa da geometria dentro de geo_data_versions
 * (apenas o hash) — ver `change_summary`/`metadata` para o processo
 * de restauro documentado pela aplicacao host.
 */
final class RollbackDataCommand extends Command
{
    protected $signature = 'geoguard:rollback-data {version : Rotulo da versao alvo (ex.: angola-boundaries-v1.0.0)} {--by=cli : Identificador de quem executa o rollback}';

    protected $description = 'Reverte o estado publicado dos dados territoriais para uma versao anterior.';

    public function handle(): int
    {
        $targetLabel = (string) $this->argument('version');

        $target = GeoDataVersion::query()->where('version_label', $targetLabel)->first();

        if ($target === null) {
            $this->components->error(sprintf('Versao "%s" nao encontrada.', $targetLabel));

            return self::FAILURE;
        }

        $current = GeoDataVersion::query()
            ->where('entity_type', $target->entity_type)
            ->where('status', 'published')
            ->latest('id')
            ->first();

        if ($current !== null && $current->id === $target->id) {
            $this->components->info('A versao indicada ja e a versao publicada atual.');

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive() || $this->confirm(sprintf('Reverter de "%s" para "%s"?', $current?->version_label ?? 'nenhuma', $targetLabel))) {
            $current?->update(['status' => 'rolled_back', 'rolled_back_at' => now()]);
            $target->update(['status' => 'published', 'published_at' => now(), 'published_by' => (string) $this->option('by')]);

            event(new GeoDataRolledBack(
                fromVersionLabel: $current?->version_label ?? 'n/a',
                toVersionLabel: $targetLabel,
                rolledBackBy: (string) $this->option('by'),
            ));

            $this->components->info(sprintf('Rollback concluido. Versao ativa: "%s".', $targetLabel));

            return self::SUCCESS;
        }

        $this->components->warn('Rollback cancelado.');

        return self::SUCCESS;
    }
}
