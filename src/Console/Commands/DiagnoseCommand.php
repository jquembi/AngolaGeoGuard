<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use JoseQuembi\AngolaGeoGuard\Contracts\GeolocationProviderInterface;
use JoseQuembi\AngolaGeoGuard\Contracts\SpatialEngineInterface;
use JoseQuembi\AngolaGeoGuard\Location\Providers\NullGeolocationProvider;
use JoseQuembi\AngolaGeoGuard\Models\Province;

/**
 * Diagnostico de saude do pacote: config, ligacao a BD, provedor de
 * geolocalizacao ativo, motor espacial, e presenca de geometrias
 * importadas. Ver secao 22/37.
 */
final class DiagnoseCommand extends Command
{
    protected $signature = 'geoguard:diagnose';

    protected $description = 'Executa um diagnostico de saude e configuracao do angola-geoguard.';

    public function handle(): int
    {
        $this->components->info('Diagnostico Angola GeoGuard');

        $rows = [];
        $failures = 0;

        $rows[] = ['Pacote ativo', config('angola-geoguard.enabled') ? 'sim' : 'nao'];
        $rows[] = ['Modo padrao', (string) config('angola-geoguard.default_mode')];
        $rows[] = ['FailureMode', (string) config('angola-geoguard.failure_mode')];

        try {
            $provinceCount = Province::query()->count();
            $rows[] = ['Provincias na base de dados', (string) $provinceCount];

            if ($provinceCount === 0) {
                $failures++;
                $rows[] = ['[AVISO]', 'Nenhuma provincia semeada. Corra: php artisan geoguard:seed-angola'];
            }

            $withGeometry = Province::query()->whereNotNull('geometry')->count();
            $rows[] = ['Provincias com geometria importada', sprintf('%d/%d', $withGeometry, $provinceCount)];

            if ($withGeometry === 0) {
                $rows[] = ['[AVISO]', 'Nenhuma geometria importada. isInsideProvince() nao funcionara ate importares fronteiras oficiais.'];
            }
        } catch (\Throwable $e) {
            $failures++;
            $rows[] = ['[ERRO] Base de dados', $e->getMessage()];
        }

        try {
            $engine = app(SpatialEngineInterface::class);
            $rows[] = ['Motor espacial ativo', $engine->name()];
        } catch (\Throwable $e) {
            $failures++;
            $rows[] = ['[ERRO] Motor espacial', $e->getMessage()];
        }

        try {
            $provider = app(GeolocationProviderInterface::class);
            $isNull = $provider instanceof NullGeolocationProvider
                || str_contains($provider->name(), 'null');
            $rows[] = ['Provedor de geolocalizacao', $provider->name()];

            if ($isNull) {
                $rows[] = ['[AVISO]', 'Nenhum provedor real configurado — resolucao por IP nao funcionara. Ver README > Provedores.'];
            }
        } catch (\Throwable $e) {
            $failures++;
            $rows[] = ['[ERRO] Provedor de geolocalizacao', $e->getMessage()];
        }

        $trustedProxies = (array) config('angola-geoguard.security.trusted_proxies', []);
        $rows[] = ['Proxies confiaveis configurados', empty($trustedProxies) ? '0 (X-Forwarded-For sera sempre ignorado)' : (string) count($trustedProxies)];

        $tokenKey = config('angola-geoguard.security.location_token.key');
        $rows[] = ['Chave de token de localizacao definida', $tokenKey ? 'sim' : 'NAO — geo.verified nao funcionara'];

        $this->table(['Verificacao', 'Resultado'], $rows);

        if ($failures > 0) {
            $this->components->error(sprintf('%d verificacao(oes) falharam.', $failures));

            return self::FAILURE;
        }

        $this->components->info('Diagnostico concluido sem erros criticos.');

        return self::SUCCESS;
    }
}
