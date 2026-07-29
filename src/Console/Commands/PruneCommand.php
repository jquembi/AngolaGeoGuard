<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JoseQuembi\AngolaGeoGuard\Contracts\BehaviorProfileRepositoryInterface;

/**
 * Aplica a politica de retencao de auditoria: apaga registos mais
 * antigos que `audit.retention_days` e anonimiza o IP de registos
 * mais antigos que `audit.anonymize_ip_after_days`. Ver secoes 25/26.
 * Tambem remove perfis comportamentais (geo_behavior_profiles) sem
 * quarentena ativa e inativos ha mais de `threat_detection.profile_retention_days`.
 *
 * Pensado para ser agendado (`php artisan schedule`) diariamente.
 */
final class PruneCommand extends Command
{
    protected $signature = 'geoguard:prune {--dry-run : Mostra o que seria apagado/anonimizado sem alterar dados}';

    protected $description = 'Aplica a politica de retencao/anonimizacao de auditoria e perfis comportamentais.';

    public function __construct(
        private readonly BehaviorProfileRepositoryInterface $behaviorProfiles,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->pruneAuditLog($dryRun);
        $this->pruneBehaviorProfiles($dryRun);

        return self::SUCCESS;
    }

    private function pruneAuditLog(bool $dryRun): void
    {
        if (! Schema::hasTable('geo_access_decisions')) {
            $this->components->warn('Tabela "geo_access_decisions" nao existe nesta aplicacao; nada a podar.');

            return;
        }

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
                [sprintf('Apagar decisoes (> %d dias)', $retentionDays), (string) $toDelete],
                [sprintf('Anonimizar IP (> %d dias)', $anonymizeAfterDays), (string) $toAnonymize],
            ],
        );

        if ($dryRun) {
            return;
        }

        DB::table('geo_access_decisions')->where('created_at', '<', $deleteCutoff)->delete();

        DB::table('geo_access_decisions')
            ->where('created_at', '<', $anonymizeCutoff)
            ->where('created_at', '>=', $deleteCutoff)
            ->whereNotNull('ip_address')
            ->update(['ip_address' => null]);

        $this->components->info('Retencao de auditoria aplicada com sucesso.');
    }

    private function pruneBehaviorProfiles(bool $dryRun): void
    {
        if (! Schema::hasTable('geo_behavior_profiles')) {
            return;
        }

        $retentionDays = (int) config('angola-geoguard.threat_detection.profile_retention_days', 90);
        $cutoff = now()->subDays($retentionDays)->toDateTimeImmutable();

        if ($dryRun) {
            $count = DB::table('geo_behavior_profiles')
                ->where('last_observed_at', '<', $cutoff)
                ->whereNull('quarantined_until')
                ->count();

            $this->components->info(sprintf('[dry-run] %d perfil(is) comportamental(is) inativo(s) seriam removidos.', $count));

            return;
        }

        $removed = $this->behaviorProfiles->pruneOlderThan($cutoff);

        $this->components->info(sprintf('%d perfil(is) comportamental(is) inativo(s) removido(s).', $removed));
    }
}
