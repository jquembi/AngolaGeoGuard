<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('geo_data_sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('responsible_entity')->nullable();
            $table->string('url')->nullable();
            $table->string('license')->nullable();
            $table->string('reference_system')->default('EPSG:4326');
            $table->string('estimated_accuracy')->nullable();
            $table->timestamp('obtained_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->string('validation_status')->default('pending'); // pending|validated|rejected
            $table->string('validated_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('geo_data_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('geo_data_source_id')->nullable()->constrained('geo_data_sources')->nullOnDelete();

            // Ex.: angola-boundaries-v1.0.0, angola-boundaries-2026-01
            $table->string('version_label')->unique();
            $table->string('entity_type'); // province|municipality|commune|...

            $table->string('previous_hash')->nullable();
            $table->string('record_hash');

            $table->string('status')->default('draft'); // draft|published|rolled_back
            $table->string('created_by')->nullable();
            $table->string('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();

            $table->json('change_summary')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['entity_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_data_versions');
        Schema::dropIfExists('geo_data_sources');
    }
};
