<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Model;
use JoseQuembi\AngolaGeoGuard\Contracts\SpatialEngineInterface;
use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidGeometryException;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;
use Ramsey\Uuid\Uuid;

/**
 * @property int         $id
 * @property string      $uuid
 * @property string      $name
 * @property string      $slug
 * @property string      $shape_type
 * @property array|null  $geometry
 * @property float|null  $center_latitude
 * @property float|null  $center_longitude
 * @property int|null    $radius_meters
 * @property array|null  $bounding_box
 * @property string|null $tenant_id
 * @property bool        $is_active
 */
final class Geofence extends Model
{
    protected $table = 'geo_geofences';

    protected $fillable = [
        'uuid', 'name', 'slug', 'description', 'shape_type', 'geometry',
        'center_latitude', 'center_longitude', 'radius_meters',
        'bounding_box', 'tenant_id', 'is_active', 'metadata',
        'geo_data_version_id',
    ];

    protected $casts = [
        'geometry' => 'array',
        'bounding_box' => 'array',
        'metadata' => 'array',
        'center_latitude' => 'float',
        'center_longitude' => 'float',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $geofence): void {
            $geofence->uuid ??= Uuid::uuid4()->toString();
        });
    }

    /**
     * Avalia se um ponto esta dentro deste geofence, delegando o
     * calculo geometrico ao motor espacial configurado.
     */
    public function containsPoint(Coordinates $point, SpatialEngineInterface $engine): bool
    {
        return match ($this->shape_type) {
            'polygon', 'multipolygon', 'corridor' => $this->containsViaGeometry($point, $engine),
            'circle' => $this->containsViaCircle($point, $engine),
            'bounding_box' => $this->containsViaBoundingBox($point),
            default => throw InvalidGeometryException::unsupportedType($this->shape_type),
        };
    }

    private function containsViaGeometry(Coordinates $point, SpatialEngineInterface $engine): bool
    {
        if (empty($this->geometry)) {
            throw InvalidGeometryException::malformed(sprintf('geofence "%s" sem geometria definida', $this->slug));
        }

        return $engine->pointInPolygon($point, $this->geometry);
    }

    private function containsViaCircle(Coordinates $point, SpatialEngineInterface $engine): bool
    {
        if ($this->center_latitude === null || $this->center_longitude === null || $this->radius_meters === null) {
            throw InvalidGeometryException::malformed(sprintf('geofence "%s" do tipo circle sem centro/raio definidos', $this->slug));
        }

        $center = new Coordinates($this->center_latitude, $this->center_longitude);

        return $engine->isWithinRadius($point, $center, (float) $this->radius_meters);
    }

    private function containsViaBoundingBox(Coordinates $point): bool
    {
        if (empty($this->bounding_box)) {
            throw InvalidGeometryException::malformed(sprintf('geofence "%s" do tipo bounding_box sem limites definidos', $this->slug));
        }

        $box = $this->bounding_box;

        return $point->latitude >= $box['min_latitude']
            && $point->latitude <= $box['max_latitude']
            && $point->longitude >= $box['min_longitude']
            && $point->longitude <= $box['max_longitude'];
    }
}
