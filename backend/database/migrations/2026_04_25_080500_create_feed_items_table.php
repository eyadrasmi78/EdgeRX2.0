<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type'); // FeedType: NEW_PRODUCT|NEW_SUPPLIER|STOCK_UPDATE|CUSTOMER_REQUEST|ADVERTISEMENT|NEWS
            $table->string('title');
            $table->text('description');
            $table->timestamp('timestamp');
            $table->string('author_id');
            $table->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('author_name');
            $table->string('author_role');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('expiry_date')->nullable();
            // metadata: productId, productImage, supplierId, stockStatus, price, newsUrl, mediaUrl, mediaType, attachmentName
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['type', 'timestamp']);
            $table->index('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
