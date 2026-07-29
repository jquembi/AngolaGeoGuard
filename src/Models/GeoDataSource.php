<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

/**
 * Regista a proveniencia de um conjunto de dados geograficos
 * (ver secao 5 do prompt mestre).
 *
 * @property int                             $id
 * @property string                          $uuid
 * @property string                          $name
 * @property string|null                     $responsible_entity
 * @property string|null                     $url
 * @property string|null                     $license
 * @property string                          $reference_system
 * @property string                          $validation_status
 * @property \Illuminate\Support\Carbon|null $last_updated_at
 */
final class GeoDataSource extends Model
{
    protected $table = 'geo_data_sources';

    protected $fillable = [
        'uuid', 'name', 'responsible_entity', 'url', 'license',
        'reference_system', 'estimated_accuracy', 'obtained_at',
        'last_updated_at', 'validation_status', 'validated_by', 'notes',
    ];

    protected $casts = [
        'obtained_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $source): void {
            $source->uuid ??= Uuid::uuid4()->toString();
        });
    }

    public function versions(): HasMany
    {
        return $this->hasMany(GeoDataVersion::class);
    }

    public function isValidated(): bool
    {
        return $this->validation_status === 'validated';
    }
}
