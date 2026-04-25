<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partnership_requests', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('from_agent_id');
            $table->foreign('from_agent_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('from_agent_name');
            $table->string('to_foreign_supplier_id');
            $table->foreign('to_foreign_supplier_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('status')->default('PENDING'); // PENDING | ACCEPTED | REJECTED
            $table->timestamp('date');
            $table->text('message')->nullable();
            $table->string('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('request_type')->default('GENERAL_CONNECTION'); // GENERAL_CONNECTION | PRODUCT_INTEREST
            $table->timestamps();

            $table->index(['from_agent_id', 'to_foreign_supplier_id', 'product_id'], 'partnership_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_requests');
    }
};
