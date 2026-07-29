<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use JoseQuembi\AngolaGeoGuard\Exceptions\InvalidGeometryException;
use JoseQuembi\AngolaGeoGuard\Models\Province;
use JoseQuembi\AngolaGeoGuard\Spatial\InMemorySpatialEngine;
use JoseQuembi\AngolaGeoGuard\ValueObjects\Coordinates;

/**
 * Valida a integridade estrutural das geometrias ja armazenadas
 * (tipo suportado, aneis fechados, sem coordenadas fora de intervalo)
 * e deteta sobreposicoes anormais entre provincias. Ver secao 22.
 */
final class ValidateCommand extends Command
{
    protected $signature = 'geoguard:validate';

    protected $description = 'Valida a integridade das geometrias das provincias ja importadas.';

    public function handle(): int
    {
        /** @var Collection<int, Province> $provinces */
        $provinces = Province::query()->whereNotNull('geometry')->get();

        if ($provinces->isEmpty()) {
            $this->components->warn('Nenhuma provincia com geometria importada. Nada a validar.');

            return self::SUCCESS;
        }

        $engine = new InMemorySpatialEngine();
        $errors = 0;
        $rows = [];

        foreach ($provinces as $province) {
            try {
                if ($province->geometry === null) {
                    throw InvalidGeometryException::malformed('provincia sem geometria');
                }

                $this->assertRingsClosed($province->geometry);
                $testPoint = new Coordinates(0, 0);
                $engine->pointInPolygon($testPoint, $province->geometry); // forca validacao estrutural
                $rows[] = [$province->official_name, 'OK'];
            } catch (\Throwable $e) {
                $errors++;
                $rows[] = [$province->official_name, 'ERRO: '.$e->getMessage()];
            }
        }

        $this->table(['Provincia', 'Resultado'], $rows);

        $overlaps = $this->detectAbnormalOverlaps($provinces, $engine);

        if (! empty($overlaps)) {
            $this->components->warn('Sobreposicoes detetadas entre provincias (verificar fronteiras):');
            $this->table(['Provincia A', 'Provincia B'], $overlaps);
        }

        if ($errors > 0) {
            $this->components->error(sprintf('%d geometria(s) invalida(s).', $errors));

            return self::FAILURE;
        }

        $this->components->info('Todas as geometrias sao estruturalmente validas.');

        return self::SUCCESS;
    }

    private function assertRingsClosed(array $geometry): void
    {
        $rings = $geometry['type'] === 'Polygon'
            ? $geometry['coordinates']
            : array_merge(...$geometry['coordinates']);

        foreach ($rings as $ring) {
            if ($ring[0] !== $ring[array_key_last($ring)]) {
                throw InvalidGeometryException::malformed('anel de coordenadas nao fechado (primeiro != ultimo ponto)');
            }
        }
    }

    /**
     * @param  Collection<int, Province>         $provinces
     * @return array<int, array{string, string}>
     */
    private function detectAbnormalOverlaps(Collection $provinces, InMemorySpatialEngine $engine): array
    {
        $overlaps = [];
        $list = $provinces->values();

        for ($i = 0; $i < count($list); $i++) {
            for ($j = $i + 1; $j < count($list); $j++) {
                try {
                    if ($list[$i]->geometry !== null && $list[$j]->geometry !== null && $engine->intersects($list[$i]->geometry, $list[$j]->geometry)) {
                        $overlaps[] = [$list[$i]->official_name, $list[$j]->official_name];
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $overlaps;
    }
}
