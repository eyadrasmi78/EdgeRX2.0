<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_details', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->string('country')->nullable();

            $table->string('trade_license_number')->nullable();
            $table->date('trade_license_expiry')->nullable();
            $table->string('trade_license_file_name')->nullable();
            $table->longText('trade_license_data_url')->nullable(); // base64 dataURL (matches prototype)

            $table->string('authorized_signatory')->nullable();
            $table->date('authorized_signatory_expiry')->nullable();
            $table->string('authorized_signatory_file_name')->nullable();
            $table->longText('authorized_signatory_data_url')->nullable();

            $table->string('business_type')->nullable(); // 'Manufacturer' | 'Authorized Marketing Supplier'
            $table->string('iso_certificate_file_name')->nullable();
            $table->date('iso_certificate_expiry')->nullable();
            $table->longText('iso_certificate_data_url')->nullable();

            $table->string('lab_test_file_name')->nullable();
            $table->longText('lab_test_data_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};
