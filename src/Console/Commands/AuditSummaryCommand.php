<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Apresenta um resumo do registo de auditoria (geo_access_decisions,
 * ver secao 25). A tabela de log de decisoes persistido e opcional —
 * este comando funciona sobre ela quando a aplicacao host a tiver
 * ativado (ver `audit.enabled` em config/angola-geoguard.php).
 */
final class AuditSummaryCommand extends Command
{
    protected $signature = 'geoguard:audit
        {--days=7 : Janela de dias a resumir}';

    protected $description = 'Mostra um resumo do registo de auditoria de decisoes geograficas.';

    public function handle(): int
    {
        if (! Schema::hasTable('geo_access_decisions')) {
            $this->components->warn(
                'A tabela "geo_access_decisions" ainda nao existe nesta aplicacao. '.
                'A persistencia de auditoria por decisao e opcional e deve ser adicionada '.
                'pela aplicacao host (ex.: um listener para os eventos GeoAccessAllowed/GeoAccessDenied) '.
                'quando for necessario um historico pesquisavel alem dos logs padrao.',
            );

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $since = now()->subDays($days);

        $total = DB::table('geo_access_decisions')->where('created_at', '>=', $since)->count();
        $allowed = DB::table('geo_access_decisions')->where('created_at', '>=', $since)->where('allowed', true)->count();
        $denied = $total - $allowed;

        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Janela', sprintf('ultimos %d dias', $days)],
                ['Total de verificacoes', (string) $total],
                ['Permitidas', (string) $allowed],
                ['Negadas', (string) $denied],
            ],
        );

        return self::SUCCESS;
    }
}
