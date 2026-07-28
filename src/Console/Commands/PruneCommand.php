<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aplica a politica de retencao de auditoria: apaga registos mais
 * antigos que `audit.retention_days` e anonimiza o IP de registos
 * mais antigos que `audit.anonymize_ip_after_days`. Ver secoes 25/26.
 *
 * Pensado para ser agendado (`php artisan schedule`) diariamente.
 */
final class PruneCommand extends Command
{
    protected $signature = 'geoguard:prune {--dry-run : Mostra o que seria apagado/anonimizado sem alterar dados}';

    protected $description = 'Aplica a politica de retencao/anonimizacao de auditoria configurada.';

    public function handle(): int
    {
        if (! Schema::hasTable('geo_access_decisions')) {
            $this->components->warn('Tabela "geo_access_decisions" nao existe nesta aplicacao; nada a podar.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $retentionDays = (int) config('angola-geoguard.audit.retention_days', 365);
        $anonymizeAfterDays = (int) config('angola-geoguard.audit.anonymize_ip_after_days', 30);

        $deleteCutoff = now()->subDays($retentionDays);
        $anonymizeCutoff = now()->subDays($anonymizeAfterDays);

        $toDelete = DB::table('geo_access_decisions')->where('created_at', '<', $deleteCutoff)->count();
        $toAnonymize = DB::table('geo_access_decisions')
            ->where('created_at', '<', $anonymizeCutoff)
            ->where('created_at', '>=', $deleteCutoff)
            ->whereNotNull('ip_address')
            ->count();

        $this->table(
            ['Acao', 'Registos afetados'],
            [
                [sprintf('Apagar (> %d dias)', $retentionDays), (string) $toDelete],
                [sprintf('Anonimizar IP (> %d dias)', $anonymizeAfterDays), (string) $toAnonymize],
            ],
        );

        if ($dryRun) {
            $this->components->info('Modo --dry-run: nenhuma alteracao foi aplicada.');

            return self::SUCCESS;
        }

        DB::table('geo_access_decisions')->where('created_at', '<', $deleteCutoff)->delete();

        DB::table('geo_access_decisions')
            ->where('created_at', '<', $anonymizeCutoff)
            ->where('created_at', '>=', $deleteCutoff)
            ->whereNotNull('ip_address')
            ->update(['ip_address' => null]);

        $this->components->info('Retencao de auditoria aplicada com sucesso.');

        return self::SUCCESS;
    }
}
