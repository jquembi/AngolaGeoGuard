<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

/**
 * Associa uma GeoAccessPolicy a uma entidade (utilizador, rota,
 * modulo, tenant, dominio, chave de API...). Ver secao 10.
 *
 * @property string $assignable_type
 * @property string $assignable_id
 * @property int $priority
 */
final class GeoPolicyAssignment extends Model
{
    protected $table = 'geo_policy_assignments';

    protected $fillable = [
        'uuid', 'geo_access_policy_id', 'assignable_type', 'assignable_id',
        'priority', 'tenant_id', 'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $assignment->uuid ??= Uuid::uuid4()->toString();
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(GeoAccessPolicy::class, 'geo_access_policy_id');
    }
}
