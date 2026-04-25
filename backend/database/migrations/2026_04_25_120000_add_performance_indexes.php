<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes flagged in the pre-demo code review (N14).
 * Each index covers a query path the dashboards / admin portal hit on every render.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin dashboard filters by role + status (e.g. all PENDING customers)
            $table->index(['role', 'status'], 'users_role_status_idx');
        });

        Schema::table('feed_items', function (Blueprint $table) {
            // News feed culls expired ads on every load
            $table->index('expiry_date', 'feed_items_expiry_idx');
        });

        Schema::table('partnership_requests', function (Blueprint $table) {
            // Foreign supplier inbox sorts PENDING partnership requests
            $table->index('status', 'partnership_requests_status_idx');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            // sender_id alone — for analytics / audit lookups
            $table->index('sender_id', 'chat_messages_sender_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_status_idx');
        });
        Schema::table('feed_items', function (Blueprint $table) {
            $table->dropIndex('feed_items_expiry_idx');
        });
        Schema::table('partnership_requests', function (Blueprint $table) {
            $table->dropIndex('partnership_requests_status_idx');
        });
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('chat_messages_sender_idx');
        });
    }
};
