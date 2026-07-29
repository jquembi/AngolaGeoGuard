<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use JoseQuembi\AngolaGeoGuard\Models\GeoBehaviorProfile;

/**
 * Lista sujeitos atualmente em quarentena e os que acumularam mais
 * violacoes historicas, para revisao manual por um administrador
 * (ver secao 21/29 — observabilidade e gestao de seguranca).
 */
final class ThreatsCommand extends Command
{
    protected $signature = 'geoguard:threats
        {--active-only : Mostra apenas sujeitos atualmente em quarentena}
        {--limit=25 : Numero maximo de registos a mostrar}';

    protected $description = 'Lista sujeitos em quarentena ou com historico de violacoes comportamentais.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $query = GeoBehaviorProfile::query()->orderByDesc('violation_count');

        if ($this->option('active-only')) {
            $query->whereNotNull('quarantined_until')->where('quarantined_until', '>', now());
        }

        /** @var \Illuminate\Support\Collection<int, GeoBehaviorProfile> $profiles */
        $profiles = $query->limit($limit)->get();

        if ($profiles->isEmpty()) {
            $this->components->info('Nenhum sujeito suspeito registado.');

            return self::SUCCESS;
        }

        $this->table(
            ['Sujeito (hash)', 'Violacoes', 'Em quarentena ate', 'Ultima atividade', 'Pedidos (janela)', 'Negados (janela)'],
            $profiles->map(fn (GeoBehaviorProfile $p) => [
                substr($p->subject_key, 0, 16).'...',
                (string) $p->violation_count,
                $p->quarantined_until && $p->quarantined_until->isFuture() ? $p->quarantined_until->diffForHumans() : '-',
                $p->last_observed_at?->diffForHumans() ?? '-',
                (string) $p->window_request_count,
                (string) $p->window_denied_count,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
