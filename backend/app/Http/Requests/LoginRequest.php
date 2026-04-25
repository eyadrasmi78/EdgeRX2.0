<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/auth/login. Email is intentionally NOT validated as RFC-email
 * so the prototype's `admin/admin` demo account still resolves.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string|max:255',
            'password' => 'required|string|max:255',
        ];
    }
}
