<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->string('order_id')->primary();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('order_id');
            $table->foreign('order_id')->references('order_id')->on('chat_rooms')->cascadeOnDelete();
            $table->string('sender_id');
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('sender_name');
            $table->text('text');
            $table->timestamp('timestamp');
            $table->timestamps();
            $table->index(['order_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_rooms');
    }
};
