<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard;

use Illuminate\Support\ServiceProvider;
use JoseQuembi\AngolaGeoGuard\Console\Commands\AuditSummaryCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\ClearCacheCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\DiagnoseCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\ImportCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\InstallCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\PruneCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\PublishCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\RollbackDataCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\SeedAngolaCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\SyncCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\ValidateCommand;
use JoseQuembi\AngolaGeoGuard\Console\Commands\WarmCacheCommand;
use JoseQuembi\AngolaGeoGuard\Contracts\DatabaseConnectionInterface;
use JoseQuembi\AngolaGeoGuard\Contracts\GeolocationProviderInterface;
use JoseQuembi\AngolaGeoGuard\Contracts\SpatialEngineInterface;
use JoseQuembi\AngolaGeoGuard\Core\GeoGuardManager;
use JoseQuembi\AngolaGeoGuard\Http\Middleware\EnsureRequestHasNoProxy;
use JoseQuembi\AngolaGeoGuard\Http\Middleware\EnsureRequestHasNoVpn;
use JoseQuembi\AngolaGeoGuard\Http\Middleware\EnsureRequestHasVerifiedLocation;
use JoseQuembi\AngolaGeoGuard\Http\Middleware\EnsureRequestIsFromAngola;
use JoseQuembi\AngolaGeoGuard\Http\Middleware\EnsureRequestIsFromProvince;
use JoseQuembi\AngolaGeoGuard\Http\Middleware\EnsureRequestIsFromProvinces;
use JoseQuembi\AngolaGeoGuard\Http\Middleware\EnsureRequestPassesGlobalPolicy;
use JoseQuembi\AngolaGeoGuard\Http\Middleware\EnsureRequestPassesNamedPolicy;
use JoseQuembi\AngolaGeoGuard\Location\LocationResolutionPipeline;
use JoseQuembi\AngolaGeoGuard\Location\Providers\GeolocationProviderChain;
use JoseQuembi\AngolaGeoGuard\Location\Providers\NullGeolocationProvider;
use JoseQuembi\AngolaGeoGuard\Location\Resolvers\IpLocationResolver;
use JoseQuembi\AngolaGeoGuard\Location\Resolvers\ManualLocationResolver;
use JoseQuembi\AngolaGeoGuard\Security\TrustedProxyIpResolver;
use JoseQuembi\AngolaGeoGuard\Services\GeoAccessPolicyEngine;
use JoseQuembi\AngolaGeoGuard\Services\GeoRequestEvaluator;
use JoseQuembi\AngolaGeoGuard\Spatial\InMemorySpatialEngine;
use JoseQuembi\AngolaGeoGuard\Spatial\MySqlSpatialEngine;
use JoseQuembi\AngolaGeoGuard\Spatial\PostGisSpatialEngine;
use JoseQuembi\AngolaGeoGuard\Support\LaravelDatabaseConnection;

final class AngolaGeoGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/angola-geoguard.php',
            'angola-geoguard',
        );

        $this->app->bind(DatabaseConnectionInterface::class, function ($app) {
            return new LaravelDatabaseConnection($app['db']->connection());
        });

        $this->app->singleton(SpatialEngineInterface::class, function ($app) {
            $engine = (string) $app['config']->get('angola-geoguard.spatial.engine', 'memory');

            return match ($engine) {
                'postgis' => new PostGisSpatialEngine($app->make(DatabaseConnectionInterface::class)),
                'mysql', 'mariadb' => new MySqlSpatialEngine($app->make(DatabaseConnectionInterface::class)),
                default => new InMemorySpatialEngine(),
            };
        });

        $this->app->singleton(GeoGuardManager::class, function ($app) {
            return new GeoGuardManager(
                spatialEngine: $app->make(SpatialEngineInterface::class),
                requestEvaluator: $app->make(GeoRequestEvaluator::class),
            );
        });

        $this->app->alias(GeoGuardManager::class, 'angola-geoguard');

        $this->app->singleton(TrustedProxyIpResolver::class, function ($app) {
            return new TrustedProxyIpResolver(
                trustedProxyCidrs: (array) $app['config']->get('angola-geoguard.security.trusted_proxies', []),
            );
        });

        // Nenhum provedor real e forcado por defeito (ver secao 8). A
        // aplicacao host deve fazer bind de GeolocationProviderInterface
        // para um adaptador real (ex.: MaxMind) para obter resolucao
        // efetiva por IP. Sem isso, o pacote nunca inventa localizacao.
        $this->app->bindIf(GeolocationProviderInterface::class, function ($app) {
            return new GeolocationProviderChain(
                providers: [new NullGeolocationProvider()],
                failoverEnabled: (bool) $app['config']->get('angola-geoguard.providers.failover', true),
            );
        });

        $this->app->singleton(LocationResolutionPipeline::class, function ($app) {
            return (new LocationResolutionPipeline())
                ->withResolver(new ManualLocationResolver())
                ->withResolver(new IpLocationResolver(
                    provider: $app->make(GeolocationProviderInterface::class),
                    trustedProxyResolver: $app->make(TrustedProxyIpResolver::class),
                ));
        });

        $this->app->singleton(GeoAccessPolicyEngine::class, function ($app) {
            return new GeoAccessPolicyEngine($app->make(SpatialEngineInterface::class));
        });

        $this->app->singleton(GeoRequestEvaluator::class, function ($app) {
            return new GeoRequestEvaluator(
                pipeline: $app->make(LocationResolutionPipeline::class),
                policyEngine: $app->make(GeoAccessPolicyEngine::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/angola-geoguard.php' => config_path('angola-geoguard.php'),
            ], 'angola-geoguard-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'angola-geoguard-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/angola-geoguard'),
            ], 'angola-geoguard-lang');

            $this->commands([
                InstallCommand::class,
                PublishCommand::class,
                SeedAngolaCommand::class,
                ImportCommand::class,
                ValidateCommand::class,
                SyncCommand::class,
                WarmCacheCommand::class,
                ClearCacheCommand::class,
                DiagnoseCommand::class,
                AuditSummaryCommand::class,
                PruneCommand::class,
                RollbackDataCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'angola-geoguard');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerMiddlewareAliases();
    }

    private function registerMiddlewareAliases(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('geo.angola', EnsureRequestIsFromAngola::class);
        $router->aliasMiddleware('geo.province', EnsureRequestIsFromProvince::class);
        $router->aliasMiddleware('geo.provinces', EnsureRequestIsFromProvinces::class);
        $router->aliasMiddleware('geo.global', EnsureRequestPassesGlobalPolicy::class);
        $router->aliasMiddleware('geo.policy', EnsureRequestPassesNamedPolicy::class);
        $router->aliasMiddleware('geo.no-vpn', EnsureRequestHasNoVpn::class);
        $router->aliasMiddleware('geo.no-proxy', EnsureRequestHasNoProxy::class);
        $router->aliasMiddleware('geo.verified', EnsureRequestHasVerifiedLocation::class);
    }

    public function provides(): array
    {
        return [
            SpatialEngineInterface::class,
            GeoGuardManager::class,
            'angola-geoguard',
        ];
    }
}
