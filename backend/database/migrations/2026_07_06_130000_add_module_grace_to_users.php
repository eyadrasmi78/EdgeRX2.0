<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grace window for the modular-plan transition. While `module_grace_until` is in the
 * future, an account keeps every module in its role for free (source GRACE). Set by
 * `entitlements:grant-grace` for existing accounts only; new signups have it null
 * (no grace = freemium immediately).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('module_grace_until')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('module_grace_until');
        });
    }
};
