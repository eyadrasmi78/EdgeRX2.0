<?php

namespace App\Support;

/**
 * Single source of truth for the camelCase → snake_case mapping of CompanyDetails.
 * Both AuthController::register and UsersController::update used to inline-expand
 * the same 17-field mapping — extracted here (N4) to keep the two paths in sync.
 */
final class CompanyDetailsPayload
{
    /** @return array<string, mixed> snake_case keyed payload ready for CompanyDetails::create */
    public static function fromRequest(array $cd, string $userId): array
    {
        return [
            'user_id' => $userId,
            'address' => $cd['address'] ?? null,
            'website' => $cd['website'] ?? null,
            'country' => $cd['country'] ?? null,
            'trade_license_number' => $cd['tradeLicenseNumber'] ?? null,
            'trade_license_expiry' => $cd['tradeLicenseExpiry'] ?? null,
            'trade_license_file_name' => $cd['tradeLicenseFileName'] ?? null,
            'trade_license_data_url' => $cd['tradeLicenseDataUrl'] ?? null,
            'authorized_signatory' => $cd['authorizedSignatory'] ?? null,
            'authorized_signatory_expiry' => $cd['authorizedSignatoryExpiry'] ?? null,
            'authorized_signatory_file_name' => $cd['authorizedSignatoryFileName'] ?? null,
            'authorized_signatory_data_url' => $cd['authorizedSignatoryDataUrl'] ?? null,
            'business_type' => $cd['businessType'] ?? null,
            'iso_certificate_file_name' => $cd['isoCertificateFileName'] ?? null,
            'iso_certificate_expiry' => $cd['isoCertificateExpiry'] ?? null,
            'iso_certificate_data_url' => $cd['isoCertificateDataUrl'] ?? null,
            'lab_test_file_name' => $cd['labTestFileName'] ?? null,
            'lab_test_data_url' => $cd['labTestDataUrl'] ?? null,
        ];
    }
}
