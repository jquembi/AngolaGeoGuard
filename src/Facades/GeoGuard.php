<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Facades;

use Illuminate\Support\Facades\Facade;
use JoseQuembi\AngolaGeoGuard\Core\GeoGuardManager;

/**
 * @method static bool                                                        isWithinRadius(\JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates $point, \JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates $center, float $radiusMeters)
 * @method static float                                                       distanceBetween(\JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates $a, \JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates $b)
 * @method static bool                                                        contains(array $polygon, \JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates $point)
 * @method static bool                                                        intersects(array $geometryA, array $geometryB)
 * @method static bool                                                        isInsideAngola(float $latitude, float $longitude)
 * @method static bool                                                        isInsideProvince(string $province, float $latitude, float $longitude)
 * @method static \JoseQuembi\AngolaGeoGuard\Services\GeoAccessRequestBuilder request(\Illuminate\Http\Request $request)
 *
 * @see GeoGuardManager
 */
final class GeoGuard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GeoGuardManager::class;
    }
}
