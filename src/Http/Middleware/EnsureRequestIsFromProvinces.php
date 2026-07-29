<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Services\GeoRequestEvaluator;

/**
 * Middleware `geo.provinces:<slug1>,<slug2>,...`: permite pedidos
 * originados em qualquer uma das provincias listadas.
 *
 * Route::middleware(['geo.provinces:huila,benguela,namibe'])->group(...);
 */
final class EnsureRequestIsFromProvinces extends BaseGeoMiddleware
{
    public function __construct(
        private readonly GeoRequestEvaluator $evaluator,
    ) {
    }

    public function handle(Request $request, Closure $next, string $provinceSlugs): mixed
    {
        $slugs = array_values(array_filter(array_map('trim', explode(',', $provinceSlugs))));

        $policy = GeoAccessPolicyConfig::fromArray([
            'identifier' => 'geo.provinces:'.implode(',', $slugs),
            'mode' => AccessMode::MULTIPLE_PROVINCES,
            'allowed_provinces' => $slugs,
        ]);

        $decision = $this->evaluator->evaluate($request, $policy);

        $request->attributes->set('geo_access_decision', $decision);

        if ($decision->denied() && ! $this->isObservationMode()) {
            return $this->respondBlocked($request, $decision);
        }

        return $this->next($next, $request);
    }
}
