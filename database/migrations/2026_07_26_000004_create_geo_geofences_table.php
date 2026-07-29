<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('geo_geofences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // polygon | multipolygon | circle | bounding_box | corridor
            $table->string('shape_type');

            // Geometria GeoJSON completa para polygon/multipolygon/corridor.
            $table->json('geometry')->nullable();

            // Usado quando shape_type = circle.
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();

            // Usado quando shape_type = bounding_box.
            $table->json('bounding_box')->nullable();

            $table->string('tenant_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->foreignId('geo_data_version_id')->nullable()
                ->constrained('geo_data_versions')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_geofences');
    }
};
