<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Database\Seeders;

use Illuminate\Database\Seeder;
use JoseQuembi\AngolaGeoGuard\Models\Country;
use JoseQuembi\AngolaGeoGuard\Models\GeoDataSource;
use JoseQuembi\AngolaGeoGuard\Models\GeoDataVersion;
use JoseQuembi\AngolaGeoGuard\Models\Province;

/**
 * Semeia o pais Angola e as suas 21 provincias administrativas,
 * conforme a Lei n.o 14/24 de 5 de Setembro de 2024 (Divisao
 * Politico-Administrativa), que criou as provincias do Cuando,
 * Cubango, Icolo e Bengo e Moxico Leste a partir da divisao de
 * Cuando Cubango, Luanda e Moxico.
 *
 * IMPORTANTE: os campos `geometry` e `bounding_box` sao propositadamente
 * deixados a null. Este pacote nao inclui poligonos oficiais de
 * fronteiras — estes devem ser importados de uma fonte oficial
 * (ex.: INE, IGCA) atraves do comando `geoguard:import` (Fase 2b/3).
 * Nome oficial, capital e codigo interno foram verificados por fonte
 * publica antes de serem incluidos aqui (ver `data_source`).
 *
 * Alguns `aliases` incluem codigos `shapeISO` historicos usados pela
 * fonte geoBoundaries (pre-reforma de 2024, ex.: "AO-BGU" para
 * Benguela, "AO-CNN" para Cunene) para permitir o casamento automatico
 * de features importadas de fontes de terceiros que usam essa
 * convencao — ver GeoJsonProvinceImportService e
 * tests/Integration/GeoJsonProvinceImportServiceTest.php.
 */
final class AngolaProvincesSeeder extends Seeder
{
    private const SOURCE_LABEL = 'Lei n.o 14/24 de 5 de Setembro de 2024 (Diario da Republica)';

    public function run(): void
    {
        $country = Country::query()->updateOrCreate(
            ['iso_code' => 'AO'],
            [
                'name' => 'Angola',
                'slug' => 'angola',
                'is_active' => true,
                'metadata' => ['municipalities_count' => 326, 'communes_count' => 378],
                'verified_at' => now(),
            ],
        );

        $source = GeoDataSource::query()->updateOrCreate(
            ['name' => self::SOURCE_LABEL],
            [
                'responsible_entity' => 'Assembleia Nacional de Angola',
                'reference_system' => 'EPSG:4326',
                'validation_status' => 'validated',
                'validated_by' => 'seed:AngolaProvincesSeeder',
                'obtained_at' => now(),
                'notes' => 'Dados administrativos (nomes e capitais). Nao inclui geometria/poligonos.',
            ],
        );

        $version = GeoDataVersion::query()->firstOrCreate(
            ['version_label' => 'angola-provinces-admin-2024.09'],
            [
                'geo_data_source_id' => $source->id,
                'entity_type' => 'province',
                'record_hash' => GeoDataVersion::computeHash($this->provinces()),
                'status' => 'published',
                'created_by' => 'seed:AngolaProvincesSeeder',
                'published_by' => 'seed:AngolaProvincesSeeder',
                'published_at' => now(),
                'change_summary' => ['note' => 'Seed inicial das 21 provincias, sem geometria.'],
            ],
        );

        foreach ($this->provinces() as $data) {
            Province::query()->updateOrCreate(
                ['internal_code' => $data['internal_code']],
                [
                    'geo_country_id' => $country->id,
                    'official_name' => $data['official_name'],
                    'normalized_name' => $data['normalized_name'],
                    'slug' => $data['slug'],
                    'official_code' => null,
                    'capital' => $data['capital'],
                    'latitude' => null,
                    'longitude' => null,
                    'bounding_box' => null,
                    'geometry' => null,
                    'aliases' => $data['aliases'],
                    'is_active' => true,
                    'metadata' => ['created_2024' => $data['created_2024'] ?? false],
                    'data_source' => $version->version_label,
                    'verified_at' => now(),
                ],
            );
        }
    }

    /**
     * @return array<int, array{official_name: string, normalized_name: string, slug: string, internal_code: string, capital: string, aliases: array<string>, created_2024?: bool}>
     */
    private function provinces(): array
    {
        return [
            ['official_name' => 'Bengo', 'normalized_name' => 'bengo', 'slug' => 'bengo', 'internal_code' => 'AO-BGO', 'capital' => 'Caxito', 'aliases' => ['Bengo']],
            ['official_name' => 'Benguela', 'normalized_name' => 'benguela', 'slug' => 'benguela', 'internal_code' => 'AO-BEN', 'capital' => 'Benguela', 'aliases' => ['Benguela', 'AO-BGU']],
            ['official_name' => 'Bié', 'normalized_name' => 'bie', 'slug' => 'bie', 'internal_code' => 'AO-BIE', 'capital' => 'Kuito', 'aliases' => ['Bie', 'Bié']],
            ['official_name' => 'Cabinda', 'normalized_name' => 'cabinda', 'slug' => 'cabinda', 'internal_code' => 'AO-CAB', 'capital' => 'Cabinda', 'aliases' => ['Cabinda']],
            ['official_name' => 'Cuando', 'normalized_name' => 'cuando', 'slug' => 'cuando', 'internal_code' => 'AO-CUD', 'capital' => 'Mavinga', 'aliases' => ['Cuando', 'Kuando'], 'created_2024' => true],
            ['official_name' => 'Cubango', 'normalized_name' => 'cubango', 'slug' => 'cubango', 'internal_code' => 'AO-CUB', 'capital' => 'Menongue', 'aliases' => ['Cubango', 'Kubango'], 'created_2024' => true],
            ['official_name' => 'Cuanza Norte', 'normalized_name' => 'cuanza norte', 'slug' => 'cuanza-norte', 'internal_code' => 'AO-CNO', 'capital' => "N'dalatando", 'aliases' => ['Kwanza Norte', 'Cuanza Norte']],
            ['official_name' => 'Cuanza Sul', 'normalized_name' => 'cuanza sul', 'slug' => 'cuanza-sul', 'internal_code' => 'AO-CUS', 'capital' => 'Sumbe', 'aliases' => ['Kwanza Sul', 'Cuanza Sul']],
            ['official_name' => 'Cunene', 'normalized_name' => 'cunene', 'slug' => 'cunene', 'internal_code' => 'AO-CUN', 'capital' => 'Ondjiva', 'aliases' => ['Cunene', 'AO-CNN']],
            ['official_name' => 'Huambo', 'normalized_name' => 'huambo', 'slug' => 'huambo', 'internal_code' => 'AO-HUA', 'capital' => 'Huambo', 'aliases' => ['Huambo']],
            ['official_name' => 'Huíla', 'normalized_name' => 'huila', 'slug' => 'huila', 'internal_code' => 'AO-HUI', 'capital' => 'Lubango', 'aliases' => ['Huila', 'Huíla']],
            ['official_name' => 'Icolo e Bengo', 'normalized_name' => 'icolo e bengo', 'slug' => 'icolo-e-bengo', 'internal_code' => 'AO-ICB', 'capital' => 'Catete', 'aliases' => ['Icolo e Bengo', 'Icole Bengo'], 'created_2024' => true],
            ['official_name' => 'Luanda', 'normalized_name' => 'luanda', 'slug' => 'luanda', 'internal_code' => 'AO-LUA', 'capital' => 'Luanda', 'aliases' => ['Luanda']],
            ['official_name' => 'Lunda Norte', 'normalized_name' => 'lunda norte', 'slug' => 'lunda-norte', 'internal_code' => 'AO-LNO', 'capital' => 'Dundo', 'aliases' => ['Lunda Norte']],
            ['official_name' => 'Lunda Sul', 'normalized_name' => 'lunda sul', 'slug' => 'lunda-sul', 'internal_code' => 'AO-LSU', 'capital' => 'Saurimo', 'aliases' => ['Lunda Sul']],
            ['official_name' => 'Malanje', 'normalized_name' => 'malanje', 'slug' => 'malanje', 'internal_code' => 'AO-MAL', 'capital' => 'Malanje', 'aliases' => ['Malanje']],
            ['official_name' => 'Moxico', 'normalized_name' => 'moxico', 'slug' => 'moxico', 'internal_code' => 'AO-MOX', 'capital' => 'Luena', 'aliases' => ['Moxico']],
            ['official_name' => 'Moxico Leste', 'normalized_name' => 'moxico leste', 'slug' => 'moxico-leste', 'internal_code' => 'AO-MXL', 'capital' => 'Cazombo', 'aliases' => ['Moxico Leste'], 'created_2024' => true],
            ['official_name' => 'Namibe', 'normalized_name' => 'namibe', 'slug' => 'namibe', 'internal_code' => 'AO-NAM', 'capital' => 'Moçâmedes', 'aliases' => ['Namibe', 'Moçâmedes']],
            ['official_name' => 'Uíge', 'normalized_name' => 'uige', 'slug' => 'uige', 'internal_code' => 'AO-UIG', 'capital' => 'Uíge', 'aliases' => ['Uige', 'Uíge']],
            ['official_name' => 'Zaire', 'normalized_name' => 'zaire', 'slug' => 'zaire', 'internal_code' => 'AO-ZAI', 'capital' => "M'banza Kongo", 'aliases' => ['Zaire']],
        ];
    }
}
