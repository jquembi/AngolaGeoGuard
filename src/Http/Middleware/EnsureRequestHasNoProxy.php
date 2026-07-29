<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Services\GeoRequestEvaluator;

/**
 * Middleware `geo.no-proxy`: bloqueia apenas proxy, sem restringir
 * por pais/provincia.
 */
final class EnsureRequestHasNoProxy extends BaseGeoMiddleware
{
    public function __construct(
        private readonly GeoRequestEvaluator $evaluator,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $policy = GeoAccessPolicyConfig::fromArray([
            'identifier' => 'geo.no-proxy',
            'mode' => AccessMode::GLOBAL,
            'block_proxy' => true,
            'block_tor' => (bool) config('angola-geoguard.security.block_tor', true),
        ]);

        $decision = $this->evaluator->evaluate($request, $policy);
        $request->attributes->set('geo_access_decision', $decision);

        if ($decision->denied() && ! $this->isObservationMode()) {
            return $this->respondBlocked($request, $decision);
        }

        return $this->next($next, $request);
    }
}
