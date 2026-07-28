<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use JoseQuembi\AngolaGeoGuard\Security\LocationToken;

/**
 * Middleware `geo.verified`: exige um LocationToken assinado
 * (cabecalho `X-Location-Token`), verificando assinatura HMAC,
 * expiracao e protecao contra replay via cache. Ver secao 13.
 *
 * Pensado para rotas de alto risco onde a localizacao aproximada por
 * IP nao e suficiente e a aplicacao cliente (movel/navegador) deve
 * fornecer GPS assinado pelo backend.
 */
final class EnsureRequestHasVerifiedLocation extends BaseGeoMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-Location-Token');
        $signingKey = (string) config('angola-geoguard.security.location_token.key');

        if (empty($token) || empty($signingKey)) {
            return $this->respondUnverified($request);
        }

        $nonceSeenBefore = function (string $nonce): bool {
            $cacheKey = 'geoguard:location-token-nonce:'.$nonce;

            if (Cache::has($cacheKey)) {
                return true;
            }

            Cache::put($cacheKey, true, (int) config('angola-geoguard.security.location_token.ttl_seconds', 300));

            return false;
        };

        $verified = LocationToken::verify($token, $signingKey, $nonceSeenBefore);

        if ($verified === null) {
            return $this->respondUnverified($request);
        }

        $request->attributes->set('geo_verified_location_token', $verified);

        return $this->next($next, $request);
    }

    private function respondUnverified(Request $request)
    {
        $statusCode = (int) config('angola-geoguard.responses.status_code', 403);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'E necessaria uma verificacao de localizacao valida para aceder a este recurso.',
                'code' => 'GEO_VERIFICATION_REQUIRED',
            ], $statusCode);
        }

        return response('E necessaria uma verificacao de localizacao valida.', $statusCode);
    }
}
