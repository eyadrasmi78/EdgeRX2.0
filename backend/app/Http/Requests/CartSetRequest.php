<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PUT /api/cart. Replaces the user's whole cart with `items`.
 */
class CartSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|max:200',
            'items.*.productId' => 'required|string|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
        ];
    }
}
