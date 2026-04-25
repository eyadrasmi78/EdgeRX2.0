<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDetails extends Model
{
    protected $table = 'company_details';

    protected $fillable = [
        'user_id',
        'address', 'website', 'country',
        'trade_license_number', 'trade_license_expiry',
        'trade_license_file_name', 'trade_license_data_url',
        'authorized_signatory', 'authorized_signatory_expiry',
        'authorized_signatory_file_name', 'authorized_signatory_data_url',
        'business_type',
        'iso_certificate_file_name', 'iso_certificate_expiry', 'iso_certificate_data_url',
        'lab_test_file_name', 'lab_test_data_url',
    ];

    protected $casts = [
        'trade_license_expiry' => 'date',
        'authorized_signatory_expiry' => 'date',
        'iso_certificate_expiry' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
