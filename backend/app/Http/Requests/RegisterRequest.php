<?php

namespace App\Http\Requests;

use App\Rules\DocumentDataUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/auth/register. Email must be unique across users + team_members
 * so the auth lookup in AuthController::login can't pick the wrong row. Base64
 * data URLs are capped at ~7.5MB raw to stop OOM attacks; BE-38 adds magic-byte
 * MIME validation so a renamed .exe can't pass through as a "trade license".
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pdfRule  = ['nullable', 'string', 'max:10000000', new DocumentDataUrl(['pdf'])];
        $imgOrPdf = ['nullable', 'string', 'max:10000000', new DocumentDataUrl(['pdf', 'image'])];

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|max:255|unique:users,email|unique:team_members,email',
            'password' => 'required|string|min:8|max:255',
            'phone' => 'nullable|string|max:64',
            'role' => 'required|in:CUSTOMER,SUPPLIER,FOREIGN_SUPPLIER',
            'companyDetails' => 'nullable|array',
            'companyDetails.address' => 'nullable|string|max:512',
            'companyDetails.website' => 'nullable|string|max:255',
            'companyDetails.country' => 'nullable|string|max:100',
            'companyDetails.tradeLicenseNumber' => 'nullable|string|max:100',
            'companyDetails.tradeLicenseExpiry' => 'nullable|date',
            'companyDetails.tradeLicenseFileName' => 'nullable|string|max:255',
            'companyDetails.tradeLicenseDataUrl' => $pdfRule,
            'companyDetails.authorizedSignatory' => 'nullable|string|max:255',
            'companyDetails.authorizedSignatoryExpiry' => 'nullable|date',
            'companyDetails.authorizedSignatoryFileName' => 'nullable|string|max:255',
            'companyDetails.authorizedSignatoryDataUrl' => $pdfRule,
            'companyDetails.businessType' => 'nullable|string|max:100',
            'companyDetails.isoCertificateFileName' => 'nullable|string|max:255',
            'companyDetails.isoCertificateExpiry' => 'nullable|date',
            'companyDetails.isoCertificateDataUrl' => $imgOrPdf,
            'companyDetails.labTestFileName' => 'nullable|string|max:255',
            'companyDetails.labTestDataUrl' => $imgOrPdf,
        ];
    }
}
