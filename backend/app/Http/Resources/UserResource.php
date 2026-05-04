<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewer = $request->user();
        $cd = $this->companyDetails;

        // Privacy gate (BE-3 / CRIT-2 / CRIT-5):
        // Regulatory PDFs (trade-license, signatory, ISO, lab-test) are PII +
        // sensitive business records. ONLY the user themselves and admins may
        // see the full data URLs. Everyone else gets the file-name as a UI hint
        // (so existing UI badges still render) but never the base64 payload.
        $canSeeDocs = $viewer && (
            $viewer->id === $this->id ||
            $viewer->isAdmin() ||
            // Pharmacy Master can see their child pharmacies' docs
            ($viewer->isPharmacyMaster() && $viewer->masterOf()->where('users.id', $this->id)->exists())
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'companyDetails' => $cd ? [
                'address' => $cd->address,
                'website' => $cd->website,
                'country' => $cd->country,
                'tradeLicenseNumber' => $cd->trade_license_number,
                'tradeLicenseExpiry' => optional($cd->trade_license_expiry)->toDateString(),
                'tradeLicenseFileName' => $cd->trade_license_file_name,
                'tradeLicenseDataUrl' => $canSeeDocs ? $cd->trade_license_data_url : null,
                'authorizedSignatory' => $cd->authorized_signatory,
                'authorizedSignatoryExpiry' => optional($cd->authorized_signatory_expiry)->toDateString(),
                'authorizedSignatoryFileName' => $cd->authorized_signatory_file_name,
                'authorizedSignatoryDataUrl' => $canSeeDocs ? $cd->authorized_signatory_data_url : null,
                'businessType' => $cd->business_type,
                'isoCertificateFileName' => $cd->iso_certificate_file_name,
                'isoCertificateExpiry' => optional($cd->iso_certificate_expiry)->toDateString(),
                'isoCertificateDataUrl' => $canSeeDocs ? $cd->iso_certificate_data_url : null,
                'labTestFileName' => $cd->lab_test_file_name,
                'labTestDataUrl' => $canSeeDocs ? $cd->lab_test_data_url : null,
            ] : null,
            'teamMembers' => $this->whenLoaded('teamMembers', function () {
                return $this->teamMembers->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'email' => $m->email,
                    'phone' => $m->phone,
                    'jobTitle' => $m->job_title,
                    'permissions' => $m->permissions ?? [],
                    'createdAt' => $m->created_at?->toIso8601String(),
                    // password not exposed
                ]);
            }, []),
            // For PHARMACY_MASTER users: the pharmacies they own.
            // Empty array for everyone else.
            'childPharmacies' => $this->whenLoaded('masterOf', function () {
                return $this->masterOf->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'email' => $p->email,
                    'phone' => $p->phone,
                    'role' => $p->role,
                    'status' => $p->status,
                ]);
            }, []),
            // For CUSTOMER users that have a master: the master's id + name.
            'master' => $this->whenLoaded('masteredBy', function () {
                $m = $this->masteredBy->first();
                return $m ? ['id' => $m->id, 'name' => $m->name] : null;
            }, null),
        ];
    }
}
