<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Services;

use Illuminate\Support\Facades\DB;
use JoseQuembi\AngolaGeoGuard\Events\GeoDataImported;
use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidGeometryException;
use JoseQuembi\AngolaGeoGuard\Models\GeoDataSource;
use JoseQuembi\AngolaGeoGuard\Models\GeoDataVersion;
use JoseQuembi\AngolaGeoGuard\Models\Province;

/**
 * Logica de importacao de geometrias oficiais (GeoJSON), extraida do
 * comando `geoguard:import` para ser reutilizavel e testavel de forma
 * isolada (ver secao 22). Fontes reais de terceiros raramente seguem
 * exatamente a convencao interna do pacote — por isso o casamento de
 * provincias tenta varias chaves candidatas (`internal_code`, `slug`,
 * `shapeISO` — convencao comum do geoBoundaries — e `aliases`),
 * sempre relatando explicitamente o que nao foi possivel casar em vez
 * de assumir ou inventar uma correspondencia.
 */
final class GeoJsonProvinceImportService
{
    /**
     * @return array<int, array{key: string, geometry: array}>
     */
    public function parseAndValidate(string $filePath): array
    {
        $json = file_get_contents($filePath);

        if ($json === false) {
            throw InvalidGeometryException::malformed('nao foi possivel ler o ficheiro');
        }

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw InvalidGeometryException::malformed('JSON invalido: '.$e->getMessage());
        }

        if (($data['type'] ?? null) !== 'FeatureCollection' || ! is_array($data['features'] ?? null)) {
            throw InvalidGeometryException::malformed('esperado um GeoJSON do tipo FeatureCollection');
        }

        $features = [];

        foreach ($data['features'] as $index => $feature) {
            $geometry = $feature['geometry'] ?? null;
            $type = $geometry['type'] ?? null;

            if (! in_array($type, ['Polygon', 'MultiPolygon'], true)) {
                throw InvalidGeometryException::unsupportedType(sprintf('feature #%d: %s', $index, (string) $type));
            }

            if (! isset($geometry['coordinates']) || ! is_array($geometry['coordinates'])) {
                throw InvalidGeometryException::malformed(sprintf('feature #%d sem coordenadas validas', $index));
            }

            $properties = $feature['properties'] ?? [];

            // Ordem de preferencia de chave: convencoes do proprio pacote
            // primeiro, depois convencoes comuns de fontes de terceiros
            // (geoBoundaries usa `shapeISO`), com fallback posicional.
            $key = $properties['internal_code']
                ?? $properties['slug']
                ?? $properties['shapeISO']
                ?? $properties['name']
                ?? ('feature-'.$index);

            $features[] = ['key' => (string) $key, 'geometry' => $geometry, 'raw_properties' => $properties];
        }

        return $features;
    }

    /**
     * @param  array<int, array{key: string, geometry: array}>  $features
     * @return array<int, array{feature_key: string, geometry: array, province: ?Province}>
     */
    public function matchProvinces(array $features): array
    {
        $all = Province::query()->get();
        $byInternalCode = $all->keyBy('internal_code');
        $bySlug = $all->keyBy('slug');

        // Indice de aliases: cada provincia pode ter varios codigos
        // historicos/alternativos (ex.: Benguela tambem conhecida por
        // "AO-BGU" numa fonte pre-2024). Comparacao case-insensitive.
        $byAlias = [];
        foreach ($all as $province) {
            foreach ((array) ($province->aliases ?? []) as $alias) {
                $byAlias[mb_strtolower((string) $alias)] = $province;
            }
        }

        $matched = [];

        foreach ($features as $feature) {
            $province = $byInternalCode->get($feature['key'])
                ?? $bySlug->get($feature['key'])
                ?? $byAlias[mb_strtolower($feature['key'])]
                ?? null;

            $matched[] = [
                'feature_key' => $feature['key'],
                'geometry' => $feature['geometry'],
                'province' => $province,
            ];
        }

        return $matched;
    }

    /**
     * @param  array<int, array{feature_key: string, geometry: array, province: ?Province}>  $matched
     */
    public function persist(array $matched, string $sourceName, string $versionLabel): GeoDataVersion
    {
        $version = DB::transaction(function () use ($matched, $sourceName, $versionLabel) {
            $source = GeoDataSource::query()->firstOrCreate(
                ['name' => $sourceName],
                ['validation_status' => 'validated', 'obtained_at' => now(), 'reference_system' => 'EPSG:4326'],
            );

            $lastVersion = GeoDataVersion::query()
                ->where('entity_type', 'province')
                ->latest('id')
                ->first();

            $payload = array_map(fn ($m) => ['key' => $m['feature_key'], 'geometry' => $m['geometry']], $matched);

            $version = GeoDataVersion::create([
                'geo_data_source_id' => $source->id,
                'version_label' => $versionLabel,
                'entity_type' => 'province',
                'previous_hash' => $lastVersion?->record_hash,
                'record_hash' => GeoDataVersion::computeHash($payload),
                'status' => 'published',
                'created_by' => 'service:GeoJsonProvinceImportService',
                'published_by' => 'service:GeoJsonProvinceImportService',
                'published_at' => now(),
                'change_summary' => ['imported_features' => count($matched)],
            ]);

            foreach ($matched as $item) {
                if ($item['province'] === null) {
                    continue;
                }

                $item['province']->update([
                    'geometry' => $item['geometry'],
                    'data_source' => $version->version_label,
                    'verified_at' => now(),
                ]);
            }

            return $version;
        });

        if (function_exists('event')) {
            event(new GeoDataImported(
                versionLabel: $versionLabel,
                entityType: 'province',
                recordCount: count(array_filter($matched, fn ($m) => $m['province'] !== null)),
            ));
        }

        return $version;
    }
}
