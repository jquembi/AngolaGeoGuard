<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Territory;
use Ramsey\Uuid\Uuid;

/**
 * @property int $id
 * @property string $uuid
 * @property int $geo_country_id
 * @property string $official_name
 * @property string $normalized_name
 * @property string $slug
 * @property string $internal_code
 * @property string|null $official_code
 * @property string|null $capital
 * @property float|null $latitude
 * @property float|null $longitude
 * @property array|null $bounding_box
 * @property array|null $geometry
 * @property array|null $aliases
 * @property bool $is_active
 * @property array|null $metadata
 */
final class Province extends Model
{
    protected $table = 'geo_provinces';

    protected $fillable = [
        'uuid', 'geo_country_id', 'official_name', 'normalized_name', 'slug',
        'internal_code', 'official_code', 'capital', 'latitude', 'longitude',
        'bounding_box', 'geometry', 'aliases', 'is_active', 'metadata',
        'data_source', 'verified_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'bounding_box' => 'array',
        'geometry' => 'array',
        'aliases' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $province): void {
            $province->uuid ??= Uuid::uuid4()->toString();
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'geo_country_id');
    }

    /**
     * Indica se a provincia ja possui geometria oficial importada,
     * permitindo point-in-polygon real em vez de apenas bounding box.
     */
    public function hasGeometry(): bool
    {
        return ! empty($this->geometry);
    }

    public function toCoordinates(): ?Coordinates
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return new Coordinates($this->latitude, $this->longitude);
    }

    public function toTerritory(): Territory
    {
        return new Territory(
            internalCode: $this->internal_code,
            slug: $this->slug,
            officialName: $this->official_name,
            level: 'province',
            officialCode: $this->official_code,
            parentCode: 'AO',
        );
    }
}
