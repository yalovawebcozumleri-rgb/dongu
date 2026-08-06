<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'province_id' => ['nullable', 'required_with:district_id,neighborhood', 'integer', Rule::exists('provinces', 'id')],
            'district_id' => [
                'nullable',
                'required_with:province_id,neighborhood',
                'integer',
                Rule::exists('districts', 'id')->where(
                    fn ($query) => $query->where('province_id', $this->integer('province_id'))
                ),
            ],
            'neighborhood' => ['nullable', 'required_with:province_id,district_id', 'string', 'min:2', 'max:100'],
            'public_area' => ['nullable', 'required_without_all:province_id,district_id,neighborhood', 'string', 'min:2', 'max:120'],
            'full_address' => ['required', 'string', 'min:10', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'delivery_notes' => ['nullable', 'string', 'max:300'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'province_id.required' => 'İl seçmelisin.',
            'province_id.exists' => 'Seçilen il geçerli değil.',
            'district_id.required' => 'İlçe seçmelisin.',
            'district_id.exists' => 'Seçilen ilçe bu ile bağlı değil.',
            'neighborhood.required' => 'Mahalle bilgisini yazmalısın.',
            'full_address.required' => 'Tam teslimat adresini yazmalısın.',
        ];
    }
}
