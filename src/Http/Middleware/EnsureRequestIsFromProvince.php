<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Services\GeoRequestEvaluator;

/**
 * Middleware `geo.province:<slug>`: permite apenas pedidos originados
 * numa unica provincia.
 *
 * Route::middleware(['geo.province:huila'])->group(...);
 */
final class EnsureRequestIsFromProvince extends BaseGeoMiddleware
{
    public function __construct(
        private readonly GeoRequestEvaluator $evaluator,
    ) {
    }

    public function handle(Request $request, Closure $next, string $provinceSlug): mixed
    {
        $policy = GeoAccessPolicyConfig::fromArray([
            'identifier' => 'geo.province:'.$provinceSlug,
            'mode' => AccessMode::PROVINCE_ONLY,
            'allowed_provinces' => [$provinceSlug],
        ]);

        $decision = $this->evaluator->evaluate($request, $policy);

        $request->attributes->set('geo_access_decision', $decision);

        if ($decision->denied() && ! $this->isObservationMode()) {
            return $this->respondBlocked($request, $decision);
        }

        return $this->next($next, $request);
    }
}
