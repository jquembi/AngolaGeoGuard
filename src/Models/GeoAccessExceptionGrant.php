<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

/**
 * Excecao temporaria e auditavel de uma politica geografica. Ver
 * secao 15. Toda excecao deve ser explicita e, quando possivel,
 * temporaria — nunca um bypass permanente e silencioso.
 *
 * @property string $status
 * @property array $authorized_territories
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property int|null $usage_limit
 * @property int $usage_count
 */
final class GeoAccessExceptionGrant extends Model
{
    protected $table = 'geo_access_exceptions';

    protected $fillable = [
        'uuid', 'geo_access_policy_id', 'user_id', 'tenant_id', 'reason',
        'authorized_territories', 'starts_at', 'expires_at', 'created_by',
        'approved_by', 'status', 'usage_limit', 'usage_count',
        'ip_address', 'device_id', 'revoked_at', 'revoked_by',
    ];

    protected $casts = [
        'authorized_territories' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $exception): void {
            $exception->uuid ??= Uuid::uuid4()->toString();
            $exception->status ??= 'active';
            $exception->usage_count ??= 0;
        });

        static::created(function (self $exception): void {
            if (function_exists('event')) {
                event(new \JoseQuembi\AngolaGeoGuard\Events\GeoExceptionGranted($exception));
            }
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(GeoAccessPolicy::class, 'geo_access_policy_id');
    }

    /**
     * Verifica se a excecao esta valida no momento atual: estado
     * ativo, dentro da janela temporal, e dentro do limite de uso.
     */
    public function isCurrentlyValid(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        if ($this->status !== 'active') {
            return false;
        }

        if ($this->starts_at !== null && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at !== null && $now->gte($this->expires_at)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function coversTerritory(string $territorySlugOrGlobal): bool
    {
        return in_array('global', $this->authorized_territories, true)
            || in_array($territorySlugOrGlobal, $this->authorized_territories, true);
    }

    public function recordUsage(): void
    {
        $this->increment('usage_count');

        if (function_exists('event')) {
            event(new \JoseQuembi\AngolaGeoGuard\Events\GeoExceptionUsed($this));
        }
    }

    public function revoke(string $revokedBy): void
    {
        $this->update([
            'status' => 'revoked',
            'revoked_at' => Carbon::now(),
            'revoked_by' => $revokedBy,
        ]);

        if (function_exists('event')) {
            event(new \JoseQuembi\AngolaGeoGuard\Events\GeoExceptionRevoked($this, $revokedBy));
        }
    }
}
