<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_number')->unique();
            $table->string('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('customer_id');
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('supplier_id')->nullable()->index();
            $table->foreign('supplier_id')->references('id')->on('users')->nullOnDelete();
            $table->string('supplier_name');
            $table->integer('quantity');
            $table->integer('bonus_quantity')->nullable();
            $table->string('unit_of_measurement');
            $table->string('status'); // OrderStatus enum string
            $table->text('decline_reason')->nullable();
            $table->timestamp('date');

            // Return tracking
            $table->boolean('return_requested')->default(false);
            $table->string('return_reason')->nullable(); // DAMAGED|BROKEN|INCORRECT_DETAILS|OTHER
            $table->text('return_note')->nullable();

            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('order_history_logs', function (Blueprint $table) {
            $table->id();
            $table->string('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('timestamp');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_history_logs');
        Schema::dropIfExists('orders');
    }
};
