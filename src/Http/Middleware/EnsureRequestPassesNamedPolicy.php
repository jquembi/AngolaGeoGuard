<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\Exceptions\GeoPolicyNotFoundException;
use JoseQuembi\AngolaGeoGuard\Models\GeoAccessPolicy;
use JoseQuembi\AngolaGeoGuard\Services\GeoRequestEvaluator;

/**
 * Middleware `geo.policy:<slug>`: carrega uma GeoAccessPolicy
 * persistida (criada via painel administrativo/API) pelo seu slug e
 * aplica-a ao pedido.
 *
 * Route::middleware(['geo.policy:government-private-access'])->group(...);
 */
final class EnsureRequestPassesNamedPolicy extends BaseGeoMiddleware
{
    public function __construct(
        private readonly GeoRequestEvaluator $evaluator,
    ) {
    }

    public function handle(Request $request, Closure $next, string $policySlug): mixed
    {
        $policyModel = GeoAccessPolicy::query()
            ->where('slug', $policySlug)
            ->where('is_active', true)
            ->first();

        if ($policyModel === null) {
            throw GeoPolicyNotFoundException::forIdentifier($policySlug);
        }

        $geofenceGeometries = $policyModel->allowed_geofences
            ? \JoseQuembi\AngolaGeoGuard\Models\Geofence::query()
                ->whereIn('slug', $policyModel->allowed_geofences)
                ->pluck('geometry', 'slug')
                ->all()
            : [];

        $decision = $this->evaluator->evaluate($request, $policyModel->toConfig(), $geofenceGeometries);

        $request->attributes->set('geo_access_decision', $decision);

        if ($decision->denied() && ! $this->isObservationMode()) {
            return $this->respondBlocked($request, $decision);
        }

        return $this->next($next, $request);
    }
}
