<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Services\GeoRequestEvaluator;

/**
 * Middleware `geo.global`: permite acesso a partir de qualquer pais,
 * mas continua a aplicar analise de risco/seguranca (VPN/Proxy/Tor,
 * conforme config), auditoria e nao bloqueia por localizacao. Ver
 * secao 41.
 */
final class EnsureRequestPassesGlobalPolicy extends BaseGeoMiddleware
{
    public function __construct(
        private readonly GeoRequestEvaluator $evaluator,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $security = (array) config('angola-geoguard.security', []);

        $policy = GeoAccessPolicyConfig::fromArray([
            'identifier' => 'geo.global',
            'mode' => \JoseQuembi\AngolaGeoGuard\Enums\AccessMode::GLOBAL,
            'block_vpn' => $security['block_vpn'] ?? false,
            'block_proxy' => $security['block_proxy'] ?? false,
            'block_tor' => $security['block_tor'] ?? true,
            'block_datacenter_ip' => $security['block_datacenter_ip'] ?? false,
        ]);

        $decision = $this->evaluator->evaluate($request, $policy);

        $request->attributes->set('geo_access_decision', $decision);

        if ($decision->denied() && ! $this->isObservationMode()) {
            return $this->respondBlocked($request, $decision);
        }

        return $this->next($next, $request);
    }
}
