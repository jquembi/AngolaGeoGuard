<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Integration;

use JoseQuembi\AngolaGeoGuard\Database\Seeders\AngolaProvincesSeeder;
use JoseQuembi\AngolaGeoGuard\Models\Province;
use JoseQuembi\AngolaGeoGuard\Services\GeoJsonProvinceImportService;
use JoseQuembi\AngolaGeoGuard\Spatial\InMemorySpatialEngine;
use JoseQuembi\AngolaGeoGuard\Tests\TestCase;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Teste de integracao com uma fonte GeoJSON REAL e nao controlada por
 * este pacote: as fronteiras provinciais ADM1 de Angola publicadas
 * pelo geoBoundaries (William & Mary geoLab, CC BY 4.0), obtidas do
 * repositorio pre-LFS `wmgeolab/geoBoundaries-Archive-Pre5`.
 *
 * Esta fonte e deliberadamente "suja" no sentido positivo do termo:
 * usa a convencao de propriedades `shapeISO` (nao a convencao interna
 * do pacote), reflete a divisao administrativa ANTERIOR a reforma de
 * 2024 (18 provincias, nao 21), e usa codigos ISO que nao coincidem
 * sempre com os codigos internos do pacote (ex.: "AO-BGU" em vez de
 * "AO-BEN" para Benguela). Isto torna-o um teste muito mais honesto
 * do que um fixture artificial: verifica que o pacote lida
 * corretamente com o casamento parcial, o casamento por alias e a
 * ausencia legitima de correspondencia — sem inventar dados.
 *
 * Fonte: https://github.com/wmgeolab/geoBoundaries-Archive-Pre5
 * Licenca: CC BY 4.0 — Runfola D. et al. (2020) PLoS ONE 15(4): e0231866.
 */
final class GeoJsonProvinceImportServiceTest extends TestCase
{
    private const FIXTURE = __DIR__.'/fixtures/geoBoundaries-AGO-ADM1.geojson';

    protected function setUp(): void
    {
        parent::setUp();

        (new AngolaProvincesSeeder())->run();
    }

    public function test_fixture_file_exists_and_is_a_real_multi_megabyte_geojson(): void
    {
        $this->assertFileExists(self::FIXTURE);
        // Um fixture inventado nao teria este tamanho; confirma que
        // estamos a testar contra dados geometricos reais e completos,
        // nao um poligono de exemplo simplificado.
        $this->assertGreaterThan(1_000_000, filesize(self::FIXTURE));
    }

    public function test_parses_and_structurally_validates_all_18_real_features(): void
    {
        $service = new GeoJsonProvinceImportService();

        $features = $service->parseAndValidate(self::FIXTURE);

        $this->assertCount(18, $features);

        foreach ($features as $feature) {
            $this->assertContains($feature['geometry']['type'], ['Polygon', 'MultiPolygon']);
            $this->assertNotEmpty($feature['geometry']['coordinates']);
        }
    }

    public function test_matches_provinces_using_shapeiso_property_via_internal_code(): void
    {
        $service = new GeoJsonProvinceImportService();
        $features = $service->parseAndValidate(self::FIXTURE);
        $matched = $service->matchProvinces($features);

        // 15 das 18 features do geoBoundaries usam o mesmo codigo que o
        // internal_code do pacote (ex.: AO-HUI, AO-LUA, AO-CAB...) e
        // devem casar diretamente, sem qualquer alias.
        $directMatches = array_filter(
            $matched,
            fn ($m) => $m['province'] !== null && in_array($m['feature_key'], ['AO-BGO', 'AO-BIE', 'AO-CAB', 'AO-CNO', 'AO-CUS', 'AO-HUA', 'AO-HUI', 'AO-LUA', 'AO-LNO', 'AO-LSU', 'AO-MAL', 'AO-MOX', 'AO-NAM', 'AO-UIG', 'AO-ZAI'], true),
        );

        $this->assertCount(15, $directMatches);
    }

    public function test_matches_benguela_and_cunene_via_historical_alias(): void
    {
        $service = new GeoJsonProvinceImportService();
        $features = $service->parseAndValidate(self::FIXTURE);
        $matched = $service->matchProvinces($features);

        $byKey = collect($matched)->keyBy('feature_key');

        $this->assertSame('Benguela', $byKey->get('AO-BGU')['province']?->official_name);
        $this->assertSame('Cunene', $byKey->get('AO-CNN')['province']?->official_name);
    }

    public function test_honestly_reports_unmatched_pre_reform_kuando_kubango(): void
    {
        $service = new GeoJsonProvinceImportService();
        $features = $service->parseAndValidate(self::FIXTURE);
        $matched = $service->matchProvinces($features);

        $byKey = collect($matched)->keyBy('feature_key');

        // "AO-CCU" representa a antiga provincia unificada de Kuando
        // Kubango, entretanto dividida (2024) em Cuando + Cubango. Nao
        // existe uma correspondencia 1:1 correta e automatica — o
        // pacote deve reportar isto como NAO encontrada, em vez de
        // adivinhar qual das duas novas provincias e a "certa".
        $this->assertNull($byKey->get('AO-CCU')['province']);
    }

    public function test_the_four_2024_provinces_have_no_source_feature_and_are_untouched(): void
    {
        $service = new GeoJsonProvinceImportService();
        $features = $service->parseAndValidate(self::FIXTURE);
        $matched = $service->matchProvinces($features);

        $matchedCodes = collect($matched)
            ->pluck('province')
            ->filter()
            ->pluck('internal_code')
            ->all();

        foreach (['AO-CUD', 'AO-CUB', 'AO-ICB', 'AO-MXL'] as $newProvinceCode) {
            $this->assertNotContains(
                $newProvinceCode,
                $matchedCodes,
                sprintf('%s e uma provincia pos-2024; a fonte pre-2024 nao deveria conseguir casa-la.', $newProvinceCode),
            );
        }
    }

    public function test_persist_writes_real_geometry_and_creates_a_versioned_record(): void
    {
        $service = new GeoJsonProvinceImportService();
        $features = $service->parseAndValidate(self::FIXTURE);
        $matched = $service->matchProvinces($features);

        $version = $service->persist($matched, 'geoBoundaries AGO ADM1 (teste)', 'test-import-v1');

        $this->assertSame('published', $version->status);
        $this->assertNotNull($version->record_hash);

        $huila = Province::query()->where('internal_code', 'AO-HUI')->firstOrFail();
        $this->assertNotNull($huila->geometry);
        $this->assertSame('test-import-v1', $huila->data_source);

        // As 4 provincias pos-2024 nunca deveriam ter sido tocadas.
        $icoloEBengo = Province::query()->where('internal_code', 'AO-ICB')->firstOrFail();
        $this->assertNull($icoloEBengo->geometry);
    }

    public function test_imported_real_geometry_works_with_the_spatial_engine(): void
    {
        $service = new GeoJsonProvinceImportService();
        $features = $service->parseAndValidate(self::FIXTURE);
        $matched = $service->matchProvinces($features);
        $service->persist($matched, 'geoBoundaries AGO ADM1 (teste)', 'test-import-v2');

        $huila = Province::query()->where('internal_code', 'AO-HUI')->firstOrFail();

        $engine = new InMemorySpatialEngine();

        // Lubango, capital da Huila, deve estar dentro da geometria
        // REAL importada da provincia da Huila.
        $lubango = new Coordinates(-14.9172, 13.4925);
        $this->assertTrue($engine->pointInPolygon($lubango, $huila->geometry));

        // Luanda (capital de outra provincia, no litoral, bem distante)
        // nao deve estar dentro do poligono real da Huila.
        $luanda = new Coordinates(-8.8390, 13.2894);
        $this->assertFalse($engine->pointInPolygon($luanda, $huila->geometry));
    }
}
