<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_provinces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('geo_country_id')->constrained('geo_countries')->cascadeOnDelete();

            $table->string('official_name');
            $table->string('normalized_name');
            $table->string('slug')->unique();

            // Codigo interno estavel do pacote (ex.: AO-HUI), usado como
            // identidade ate existir correspondencia ISO/oficial validada.
            $table->string('internal_code')->unique();
            $table->string('official_code')->nullable();

            $table->string('capital')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('bounding_box')->nullable();

            // Geometria (Polygon/MultiPolygon GeoJSON). Fica null ate
            // importacao via `geoguard:import` a partir de fonte oficial.
            $table->json('geometry')->nullable();

            $table->json('aliases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            // Referencia a fonte/versao dos dados administrativos (nome,
            // capital, codigo interno), separada da versao da geometria,
            // que e controlada por geo_data_versions.
            $table->string('data_source')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index('normalized_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_provinces');
    }
};
