<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PartnershipRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        // Eager-load fromAgent + companyDetails when available so the foreign
        // supplier's UI can show country / license without a second round-trip.
        $agent = $this->whenLoaded('fromAgent', function () {
            $cd = $this->fromAgent?->companyDetails;
            return [
                'id' => $this->fromAgent->id,
                'name' => $this->fromAgent->name,
                'country' => $cd?->country,
                'website' => $cd?->website,
                'tradeLicenseNumber' => $cd?->trade_license_number,
            ];
        }, null);

        return [
            'id' => $this->id,
            'fromAgentId' => $this->from_agent_id,
            'fromAgentName' => $this->from_agent_name,
            'fromAgent' => $agent,
            'toForeignSupplierId' => $this->to_foreign_supplier_id,
            'status' => $this->status,
            'date' => optional($this->date)->toIso8601String(),
            'message' => $this->message,
            'productId' => $this->product_id,
            'productName' => $this->product_name,
            'requestType' => $this->request_type,
        ];
    }
}
