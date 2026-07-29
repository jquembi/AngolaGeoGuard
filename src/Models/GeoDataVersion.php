<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

/**
 * Versao imutavel de um conjunto de dados territoriais. Nunca deve
 * ser editada apos publicacao — uma correcao gera uma nova versao
 * com `previous_hash` a apontar para a anterior (ver secao 5).
 *
 * @property int         $id
 * @property string      $uuid
 * @property string      $version_label
 * @property string      $entity_type
 * @property string|null $previous_hash
 * @property string      $record_hash
 * @property string      $status
 *
 * @method static self create(array<string, mixed> $attributes = [])
 */
final class GeoDataVersion extends Model
{
    protected $table = 'geo_data_versions';

    protected $fillable = [
        'uuid', 'geo_data_source_id', 'version_label', 'entity_type',
        'previous_hash', 'record_hash', 'status', 'created_by',
        'published_by', 'published_at', 'rolled_back_at',
        'change_summary', 'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'rolled_back_at' => 'datetime',
        'change_summary' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->uuid ??= Uuid::uuid4()->toString();
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(GeoDataSource::class, 'geo_data_source_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Calcula o hash de integridade determinístico de um payload de
     * dados territoriais, usado para detetar alteracoes silenciosas.
     */
    public static function computeHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
