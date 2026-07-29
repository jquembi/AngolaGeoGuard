<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('geo_behavior_profiles', function (Blueprint $table) {
            $table->id();

            // Chave do sujeito: hash do IP (nunca o IP em claro), opcionalmente
            // combinado com tenant_id. Ver BehaviorTrackingService::subjectKeyFor().
            $table->string('subject_key')->unique();
            $table->string('tenant_id')->nullable()->index();

            $table->timestamp('window_started_at');
            $table->unsignedInteger('window_request_count')->default(0);
            $table->unsignedInteger('window_denied_count')->default(0);
            $table->json('distinct_provinces_in_window')->nullable();
            $table->json('distinct_countries_in_window')->nullable();

            $table->timestamp('last_observed_at')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->boolean('last_is_vpn')->default(false);
            $table->boolean('last_is_proxy')->default(false);
            $table->boolean('last_is_tor')->default(false);
            $table->unsignedInteger('flag_change_count_in_window')->default(0);

            // Linha de base aprendida (EWMA) do intervalo entre pedidos.
            $table->float('ewma_interval_seconds')->nullable();

            // Memoria de longo prazo: nunca reiniciada pela janela deslizante.
            $table->unsignedInteger('violation_count')->default(0);

            $table->timestamp('quarantined_until')->nullable()->index();
            $table->unsignedInteger('last_quarantine_duration_seconds')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_behavior_profiles');
    }
};
