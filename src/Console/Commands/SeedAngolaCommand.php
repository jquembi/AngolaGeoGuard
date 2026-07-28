<?php

declare(strict_types=1);

namespace JoseQuembi\AngolaGeoGuard\Console\Commands;

use Illuminate\Console\Command;
use JoseQuembi\AngolaGeoGuard\Database\Seeders\AngolaProvincesSeeder;

final class SeedAngolaCommand extends Command
{
    protected $signature = 'geoguard:seed-angola';

    protected $description = 'Semeia o pais Angola e as suas 21 provincias administrativas (dados oficiais, sem geometria).';

    public function handle(): int
    {
        $this->info('A semear Angola e as 21 provincias...');

        (new AngolaProvincesSeeder())->run();

        $this->info('Concluido. Use "geoguard:import" para carregar as geometrias oficiais das fronteiras.');

        return self::SUCCESS;
    }
}
