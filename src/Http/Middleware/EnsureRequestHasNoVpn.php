<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Services\GeoRequestEvaluator;

/**
 * Middleware `geo.no-vpn`: bloqueia apenas VPN, sem restringir por
 * pais/provincia. Pode ser combinado com outros middleware geo.*.
 */
final class EnsureRequestHasNoVpn extends BaseGeoMiddleware
{
    public function __construct(
        private readonly GeoRequestEvaluator $evaluator,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $policy = GeoAccessPolicyConfig::fromArray([
            'identifier' => 'geo.no-vpn',
            'mode' => AccessMode::GLOBAL,
            'block_vpn' => true,
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
