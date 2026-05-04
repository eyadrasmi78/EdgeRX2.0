<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-10 fix — protect financial / audit records from cascading user deletions.
 *
 * Originally, `users` deletion cascaded into orders, pricing_agreements,
 * transfer_requests, and pricing_agreement_versions, wiping financial history
 * needed for VAT/MoH audits. Switch the customer/supplier FKs on these tables
 * to RESTRICT (block deletion if a user has any active records).
 *
 * The application layer should soft-delete users (status = REJECTED / disabled)
 * rather than hard-delete; this migration enforces that at the DB level.
 *
 * Cascade behaviour kept where it makes sense:
 *  - orders.placed_by_user_id        nullOnDelete (kept) — placeholder is acceptable
 *  - orders.buying_group_id          nullOnDelete (kept) — group can be removed
 *  - cart_items, notifications       cascadeOnDelete (kept) — transient state
 *  - chat_rooms / chat_messages      keep as-is — tied to orders not users
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── orders: block delete if a user owns any orders ──
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['supplier_id']);
            $table->foreign('customer_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('supplier_id')->references('id')->on('users')->restrictOnDelete();
        });

        // ── pricing_agreements ──
        Schema::table('pricing_agreements', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['supplier_id']);
            $table->foreign('customer_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('supplier_id')->references('id')->on('users')->restrictOnDelete();
        });

        // ── transfer_requests ──
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropForeign(['source_user_id']);
            $table->dropForeign(['supplier_id']);
            $table->foreign('source_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('supplier_id')->references('id')->on('users')->restrictOnDelete();
            // target_user_id stays nullOnDelete (target may legitimately be removed before claim)
        });

        // ── pricing_agreement_versions: keep cascade (snapshot only, no $$$) ──
        // Already cascadesOnDelete from pricing_agreements — fine.

        // ── transfer_request_items ──
        // Already cascadesOnDelete from transfer_requests — fine, items are not standalone.

        // ── transfer_qc_inspections.inspector_id: cascadeOnDelete loses audit trail ──
        Schema::table('transfer_qc_inspections', function (Blueprint $table) {
            $table->dropForeign(['inspector_id']);
            $table->foreign('inspector_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['supplier_id']);
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('pricing_agreements', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['supplier_id']);
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropForeign(['source_user_id']);
            $table->dropForeign(['supplier_id']);
            $table->foreign('source_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('transfer_qc_inspections', function (Blueprint $table) {
            $table->dropForeign(['inspector_id']);
            $table->foreign('inspector_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
