<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Core;

use Illuminate\Http\Request;
use JoseQuembi\AngolaGeoGuard\Contracts\SpatialEngineInterface;
use JoseQuembi\AngolaGeoGuard\DTOs\GeoAccessPolicyConfig;
use JoseQuembi\AngolaGeoGuard\Enums\AccessMode;
use JoseQuembi\AngolaGeoGuard\Models\Province;
use JoseQuembi\AngolaGeoGuard\Services\GeoAccessRequestBuilder;
use JoseQuembi\AngolaGeoGuard\Services\GeoRequestEvaluator;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Ponto de entrada principal do nucleo do pacote. As operacoes
 * puramente espaciais delegam para SpatialEngineInterface; as
 * operacoes de decisao/politica delegam para GeoRequestEvaluator e
 * para o GeoAccessRequestBuilder fluido (secao 19).
 */
final class GeoGuardManager
{
    public function __construct(
        private readonly SpatialEngineInterface $spatialEngine,
        private readonly ?GeoRequestEvaluator $requestEvaluator = null,
    ) {
    }

    public function isWithinRadius(Coordinates $point, Coordinates $center, float $radiusMeters): bool
    {
        return $this->spatialEngine->isWithinRadius($point, $center, $radiusMeters);
    }

    public function distanceBetween(Coordinates $a, Coordinates $b): float
    {
        return $this->spatialEngine->distanceInMeters($a, $b);
    }

    public function contains(array $polygon, Coordinates $point): bool
    {
        return $this->spatialEngine->pointInPolygon($point, $polygon);
    }

    public function intersects(array $geometryA, array $geometryB): bool
    {
        return $this->spatialEngine->intersects($geometryA, $geometryB);
    }

    public function spatialEngineName(): string
    {
        return $this->spatialEngine->name();
    }

    /**
     * Inicia a construcao fluida de uma avaliacao de politica para um
     * pedido HTTP. Ver secao 19.
     */
    public function request(Request $request): GeoAccessRequestBuilder
    {
        if ($this->requestEvaluator === null) {
            throw new \RuntimeException('GeoRequestEvaluator nao foi injetado; disponivel apenas no contexto Laravel.');
        }

        return new GeoAccessRequestBuilder($this->requestEvaluator, $request);
    }

    /**
     * Atalho: verifica se um ponto esta dentro do territorio de
     * Angola, usando a geometria do pais (quando importada) ou, na
     * ausencia desta, a uniao das geometrias provinciais importadas.
     */
    public function isInsideAngola(float $latitude, float $longitude): bool
    {
        $point = new Coordinates($latitude, $longitude);

        /** @var \Illuminate\Support\Collection<int, Province> $provinces */
        $provinces = Province::query()
            ->whereNotNull('geometry')
            ->get();

        return $provinces->contains(
            fn (Province $province): bool => $province->geometry !== null
                && $this->spatialEngine->pointInPolygon($point, $province->geometry)
        );
    }

    public function isInsideProvince(string $province, float $latitude, float $longitude): bool
    {
        $point = new Coordinates($latitude, $longitude);

        $model = Province::query()->where('slug', $province)->first();

        if ($model === null || empty($model->geometry)) {
            return false;
        }

        /** @var array $geometry */
        $geometry = $model->geometry;

        return $this->spatialEngine->pointInPolygon($point, $geometry);
    }

    /**
     * Constroi uma GeoAccessPolicyConfig ad-hoc restrita a um conjunto
     * de provincias, pronta a ser usada com evaluate() do
     * GeoAccessPolicyEngine.
     *
     * @param array<string> $provinces
     */
    public function allowOnlyProvincesPolicy(array $provinces): GeoAccessPolicyConfig
    {
        return GeoAccessPolicyConfig::fromArray([
            'identifier' => 'allow-only-provinces',
            'mode' => AccessMode::MULTIPLE_PROVINCES,
            'allowed_provinces' => $provinces,
        ]);
    }
}
