<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('dosage_form')->nullable();
            $table->string('strength')->nullable();
            $table->string('pack_size')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->text('indication')->nullable();
            $table->string('therapeutic_class')->nullable();
            $table->string('detailed_category')->nullable();

            $table->string('product_registration_file_name')->nullable();
            $table->longText('product_registration_data_url')->nullable();

            $table->string('manufacturer');
            $table->string('supplier_name');
            $table->string('supplier_id')->nullable()->index();
            $table->foreign('supplier_id')->references('id')->on('users')->nullOnDelete();

            $table->string('category'); // Medicine | Device | Supplement | Herb | Equipment
            $table->string('category_level1')->nullable();
            $table->string('category_level2')->nullable();
            $table->string('category_level3')->nullable();

            $table->text('description');
            $table->decimal('price', 14, 2);
            $table->string('unit_of_measurement');
            $table->integer('stock_level')->default(0);
            $table->string('sku')->index();

            $table->string('image')->nullable();          // primary URL
            $table->json('images')->nullable();           // array of URLs
            $table->string('video')->nullable();

            $table->integer('bonus_threshold')->nullable();
            $table->string('bonus_type')->nullable();     // 'percentage' | 'fixed'
            $table->decimal('bonus_value', 14, 2)->nullable();

            $table->string('medical_rep_name')->nullable();
            $table->string('medical_rep_email')->nullable();
            $table->string('medical_rep_phone')->nullable();
            $table->string('medical_rep_whatsapp')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
