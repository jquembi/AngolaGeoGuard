<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Tests\Feature;

use JoseQuembi\AngolaGeoGuard\Database\Seeders\AngolaProvincesSeeder;
use JoseQuembi\AngolaGeoGuard\Models\Country;
use JoseQuembi\AngolaGeoGuard\Models\Province;
use JoseQuembi\AngolaGeoGuard\Tests\TestCase;

final class AngolaProvincesSeederTest extends TestCase
{
    public function test_it_seeds_angola_and_21_provinces(): void
    {
        (new AngolaProvincesSeeder())->run();

        $this->assertSame(1, Country::query()->where('iso_code', 'AO')->count());
        $this->assertSame(21, Province::query()->count());
    }

    public function test_it_seeds_the_four_provinces_created_in_2024(): void
    {
        (new AngolaProvincesSeeder())->run();

        $newProvinces = Province::query()
            ->whereIn('internal_code', ['AO-CUD', 'AO-CUB', 'AO-ICB', 'AO-MXL'])
            ->pluck('official_name', 'internal_code');

        $this->assertSame('Cuando', $newProvinces['AO-CUD']);
        $this->assertSame('Cubango', $newProvinces['AO-CUB']);
        $this->assertSame('Icolo e Bengo', $newProvinces['AO-ICB']);
        $this->assertSame('Moxico Leste', $newProvinces['AO-MXL']);
    }

    public function test_huila_province_has_expected_capital(): void
    {
        (new AngolaProvincesSeeder())->run();

        $huila = Province::query()->where('internal_code', 'AO-HUI')->firstOrFail();

        $this->assertSame('Lubango', $huila->capital);
        $this->assertNull($huila->geometry, 'A geometria deve permanecer null ate importacao oficial.');
    }

    public function test_seeder_is_idempotent(): void
    {
        (new AngolaProvincesSeeder())->run();
        (new AngolaProvincesSeeder())->run();

        $this->assertSame(21, Province::query()->count());
    }
}
