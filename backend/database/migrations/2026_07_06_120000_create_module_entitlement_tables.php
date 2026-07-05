<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module-entitlement + subscription + promo-code foundation (Phase 1).
 * Additive only — no existing table is touched. The gate that reads these
 * tables is inert until config('modules.enforced') is true.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catalogue of purchasable modules per role.
        Schema::create('modules', function (Blueprint $table) {
            $table->string('key')->primary();               // e.g. buying_groups
            $table->string('name');
            $table->string('role_scope');                   // CUSTOMER | MASTER | SUPPLIER | FOREIGN
            $table->decimal('monthly_price_kd', 8, 2)->default(0);
            $table->boolean('is_core')->default(false);     // core = free, auto-entitled
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['role_scope', 'is_core']);
        });

        // One row per purchased module + its billing cycle.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('account_id');
            $table->foreign('account_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('module_key');
            $table->foreign('module_key')->references('key')->on('modules')->cascadeOnDelete();
            $table->string('billing_period')->default('MONTHLY');   // MONTHLY | QUARTERLY | YEARLY
            $table->string('status')->default('ACTIVE');            // ACTIVE | EXPIRED | CANCELLED
            $table->decimal('unit_price_kd', 8, 2)->default(0);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
            $table->index(['account_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });

        // The materialised source of truth the gate reads (one lookup per request).
        Schema::create('account_module_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('account_id');
            $table->foreign('account_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('module_key');
            $table->foreign('module_key')->references('key')->on('modules')->cascadeOnDelete();
            $table->string('source');                       // CORE | PURCHASED | PROMO
            $table->boolean('active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'module_key']);
            $table->index(['account_id', 'active']);
        });

        // Admin-generated fee waivers.
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('customer_id')->nullable();      // null = open (any account)
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            $table->json('module_keys');                    // ["buying_groups","transfers"]
            $table->integer('waiver_days')->nullable();     // null = permanent while code valid
            $table->integer('max_redemptions')->default(1);
            $table->integer('redeemed_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->string('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->index('code');
        });

        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $table->string('account_id');
            $table->foreign('account_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamp('redeemed_at');
            $table->timestamps();
            $table->unique(['promo_code_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_redemptions');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('account_module_entitlements');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('modules');
    }
};
