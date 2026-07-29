<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessDecision;

/**
 * Logica partilhada por todos os middleware geo.* : construcao da
 * resposta de bloqueio (HTML ou JSON), respeitando o codigo de
 * estado e mensagem configurados, sem expor detalhes internos da
 * decisao (ver secao 27).
 */
abstract class BaseGeoMiddleware
{
    protected function respondBlocked(Request $request, GeoAccessDecision $decision): mixed
    {
        $statusCode = (int) config('angola-geoguard.responses.status_code', 403);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => config('angola-geoguard.responses.json_message', $decision->publicMessage()),
                'code' => 'GEO_ACCESS_DENIED',
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
            ], $statusCode);
        }

        $view = config('angola-geoguard.responses.view', 'angola-geoguard::blocked');

        if (view()->exists($view)) {
            return response()->view($view, ['decision' => $decision], $statusCode);
        }

        return response($decision->publicMessage(), $statusCode);
    }

    /**
     * No modo de observacao, o pedido nunca e bloqueado — apenas
     * registado — permitindo testar politicas em producao antes de
     * as aplicar a serio. Ver secao 31.
     */
    protected function isObservationMode(): bool
    {
        return (bool) config('angola-geoguard.observation_mode', false);
    }

    protected function next(Closure $next, Request $request): mixed
    {
        return $next($request);
    }
}
