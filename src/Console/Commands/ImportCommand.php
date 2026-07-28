<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidGeometryException;
use JoseQuembi\AngolaGeoGuard\Services\GeoJsonProvinceImportService;

/**
 * Importa geometrias oficiais (GeoJSON FeatureCollection) para as
 * provincias existentes, com validacao estrutural, criacao de uma
 * nova GeoDataVersion (nunca sobrescreve silenciosamente) e opcao de
 * confirmacao interativa. Ver secao 22.
 *
 * Formato esperado do GeoJSON: FeatureCollection em que cada Feature
 * tem `properties.internal_code`, `properties.slug` ou
 * `properties.shapeISO` (convencao do geoBoundaries) correspondendo
 * a uma provincia ja semeada, e `geometry` do tipo Polygon ou
 * MultiPolygon em WGS84 (EPSG:4326).
 */
final class ImportCommand extends Command
{
    protected $signature = 'geoguard:import
        {--type=province : Tipo de entidade a importar (por agora apenas "province")}
        {--file= : Caminho para o ficheiro GeoJSON}
        {--source= : Nome da fonte oficial dos dados}
        {--version= : Rotulo da versao (ex.: 2026.1)}
        {--validate : Apenas valida o ficheiro, sem importar}';

    protected $description = 'Importa geometrias oficiais de fronteiras (GeoJSON) para as provincias.';

    public function __construct(
        private readonly GeoJsonProvinceImportService $importer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = (string) $this->option('type');
        $filePath = $this->option('file');
        $sourceName = $this->option('source') ?? 'Fonte nao especificada';
        $versionLabel = $this->option('version') ?? ('angola-'.$type.'-'.now()->format('Y-m-d-His'));
        $validateOnly = (bool) $this->option('validate');

        if ($type !== 'province') {
            $this->components->error('Apenas o tipo "province" e suportado nesta versao do pacote.');

            return self::FAILURE;
        }

        if (empty($filePath) || ! is_string($filePath) || ! file_exists($filePath)) {
            $this->components->error('Ficheiro nao encontrado. Use --file=/caminho/para/dados.geojson');

            return self::FAILURE;
        }

        $this->components->info('A validar estrutura do GeoJSON...');

        try {
            $features = $this->importer->parseAndValidate($filePath);
        } catch (InvalidGeometryException $e) {
            $this->components->error('Validacao falhou: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('%d feature(s) validada(s) com sucesso.', count($features)));

        $matched = $this->importer->matchProvinces($features);
        $this->table(
            ['Feature', 'Provincia correspondida'],
            array_map(fn ($m) => [$m['feature_key'], $m['province']?->official_name ?? '[NAO ENCONTRADA]'], $matched),
        );

        $unmatched = array_filter($matched, fn ($m) => $m['province'] === null);

        if (! empty($unmatched)) {
            $this->components->warn(sprintf('%d feature(s) sem correspondencia numa provincia semeada e serao ignoradas.', count($unmatched)));
        }

        if ($validateOnly) {
            $this->components->info('Modo --validate: nenhuma alteracao foi persistida.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm(sprintf('Confirmas a importacao de %d geometria(s) como nova versao "%s"?', count($matched) - count($unmatched), $versionLabel))) {
            $this->components->warn('Importacao cancelada pelo utilizador.');

            return self::SUCCESS;
        }

        $version = $this->importer->persist($matched, $sourceName, $versionLabel);

        $this->components->info(sprintf('Importacao concluida. Versao "%s" publicada.', $version->version_label));

        return self::SUCCESS;
    }
}
