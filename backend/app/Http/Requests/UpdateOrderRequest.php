<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /api/orders/{id}. Status string list mirrors the OrderStatus enum
 * in frontend/types.ts.
 */
class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:Received,Pending Customer Approval,In Progress,Shipment On The Way,Completed,Confirmed by Customer,Return Requested,Declined,Fulfilled,Out of Stock',
            'note' => 'nullable|string|max:2000',
            'declineReason' => 'nullable|string|max:2000',
            'returnRequested' => 'nullable|boolean',
            'returnReason' => 'nullable|in:DAMAGED,BROKEN,INCORRECT_DETAILS,OTHER',
            'returnNote' => 'nullable|string|max:2000',
            'bonusQuantity' => 'nullable|integer|min:0|max:100000',
        ];
    }
}
