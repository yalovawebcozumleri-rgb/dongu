<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:2', 'max:50'],
            'public_area' => ['required', 'string', 'min:2', 'max:120'],
            'full_address' => ['required', 'string', 'min:10', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'delivery_notes' => ['nullable', 'string', 'max:300'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}