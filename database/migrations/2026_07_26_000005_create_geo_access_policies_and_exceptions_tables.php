<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('geo_access_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('mode'); // JoseQuembi\AngolaGeoGuard\Enums\AccessMode

            $table->json('allowed_countries')->nullable();
            $table->json('allowed_provinces')->nullable();
            $table->json('blocked_provinces')->nullable();
            $table->json('allowed_geofences')->nullable();

            $table->string('minimum_confidence')->default('medium');

            $table->boolean('block_vpn')->default(false);
            $table->boolean('block_proxy')->default(false);
            $table->boolean('block_tor')->default(true);
            $table->boolean('block_datacenter_ip')->default(false);
            $table->boolean('require_verified_device')->default(false);

            $table->string('tenant_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();
        });

        Schema::create('geo_policy_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('geo_access_policy_id')->constrained('geo_access_policies')->cascadeOnDelete();

            // Tipo de entidade a qual a politica se aplica: user, role,
            // route, module, tenant, domain, api_key, device, session...
            $table->string('assignable_type');
            $table->string('assignable_id');

            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('tenant_id')->nullable()->index();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['assignable_type', 'assignable_id']);
        });

        Schema::create('geo_access_exceptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('geo_access_policy_id')->nullable()->constrained('geo_access_policies')->nullOnDelete();

            $table->string('user_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();

            $table->text('reason');
            $table->json('authorized_territories'); // ex.: ['global'] ou slugs de provincia

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();

            $table->string('status')->default('active'); // active|expired|revoked
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);

            $table->string('ip_address')->nullable();
            $table->string('device_id')->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_by')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_access_exceptions');
        Schema::dropIfExists('geo_policy_assignments');
        Schema::dropIfExists('geo_access_policies');
    }
};
