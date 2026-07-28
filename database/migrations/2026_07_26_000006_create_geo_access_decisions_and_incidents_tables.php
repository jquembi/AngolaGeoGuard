<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_access_decisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->boolean('allowed');
            $table->string('reason_code');
            $table->text('reason')->nullable();

            $table->string('policy_identifier')->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();

            $table->string('country_code', 2)->nullable();
            $table->string('province_slug')->nullable();

            // Coordenadas armazenadas apenas se `audit.store_coordinates`
            // estiver ativo; ver secao 26 (minimizacao de dados).
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('confidence')->nullable();
            $table->string('risk')->nullable();

            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->boolean('is_datacenter')->default(false);

            $table->string('provider')->nullable();
            $table->string('data_version')->nullable();
            $table->string('policy_version')->nullable();

            // IP guardado conforme `audit.store_ip`; anonimizado apos
            // `audit.anonymize_ip_after_days` pelo comando geoguard:prune.
            $table->string('ip_address')->nullable();

            $table->json('evidence')->nullable();
            $table->json('warnings')->nullable();
            $table->json('applied_exceptions')->nullable();

            $table->unsignedInteger('processing_time_ms')->nullable();

            // Cadeia de hashes opcional para integridade do log
            // (ver secao 25): previous_hash aponta para o registo anterior.
            $table->string('previous_hash')->nullable();
            $table->string('record_hash')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['allowed', 'created_at']);
        });

        Schema::create('geo_security_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('type');
            $table->text('description');
            $table->string('severity')->default('medium'); // low|medium|high|critical

            $table->string('tenant_id')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address')->nullable();

            $table->json('context')->nullable();
            $table->foreignId('geo_access_decision_id')->nullable()
                ->constrained('geo_access_decisions')->nullOnDelete();

            $table->string('status')->default('open'); // open|reviewing|resolved|false_positive
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_security_incidents');
        Schema::dropIfExists('geo_access_decisions');
    }
};
