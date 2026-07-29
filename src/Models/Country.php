<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

/**
 * @property int        $id
 * @property string     $uuid
 * @property string     $iso_code
 * @property string     $name
 * @property string     $slug
 * @property array|null $bounding_box
 * @property array|null $geometry
 * @property bool       $is_active
 * @property array|null $metadata
 */
final class Country extends Model
{
    protected $table = 'geo_countries';

    protected $fillable = [
        'uuid', 'iso_code', 'name', 'slug', 'bounding_box',
        'geometry', 'is_active', 'metadata', 'verified_at',
    ];

    protected $casts = [
        'bounding_box' => 'array',
        'geometry' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $country): void {
            $country->uuid ??= Uuid::uuid4()->toString();
        });
    }

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class, 'geo_country_id');
    }

    protected function isoCode(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtoupper($value),
        );
    }
}
