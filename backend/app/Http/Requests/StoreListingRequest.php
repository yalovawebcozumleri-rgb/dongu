<?php

namespace App\Http\Requests;

use App\Models\ListingMaterial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return [
            'materials' => ['required', 'array', 'min:1', 'max:3'],
            'materials.*.type' => [
                'required',
                Rule::in([ListingMaterial::PET, ListingMaterial::GLASS, ListingMaterial::ALUMINUM]),
                'distinct',
            ],
            'materials.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'materials.*.unit_price' => [
                'required',
                'numeric',
                'min:0.05',
                'max:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_numeric($value)) {
                        return;
                    }

                    $kurus = (float) $value * 100;
                    $roundedKurus = (int) round($kurus);
                    if (abs($kurus - $roundedKurus) > 0.00001 || $roundedKurus % 5 !== 0) {
                        $fail('Adet fiyatı 5 kuruşluk artışlarla belirlenmelidir. Örneğin 0,25 TL veya 0,30 TL girebilirsin.');
                    }
                },
            ],
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'packaging_condition_confirmed' => ['required', 'accepted'],
            'address_id' => [
                'nullable',
                'integer',
                Rule::exists('user_addresses', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->id)
                ),
            ],
            'public_area' => ['required_without:address_id', 'nullable', 'string', 'max:120'],
            'latitude' => ['required_without:address_id', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_without:address_id', 'nullable', 'numeric', 'between:-180,180'],
            'exact_address' => ['required_without:address_id', 'nullable', 'string', 'min:10', 'max:500'],
            'delivery_notes' => ['nullable', 'string', 'max:300'],
            'photos' => ['sometimes', 'array', 'max:5'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $totalQuantity = collect($this->input('materials', []))->sum(function ($material): int {
                $quantity = is_array($material) ? ($material['quantity'] ?? 0) : 0;

                return is_numeric($quantity) ? (int) $quantity : 0;
            });

            if ($totalQuantity < 20) {
                $validator->errors()->add(
                    'materials',
                    "İlan yayınlayabilmek için toplam ambalaj adedi en az 20 olmalıdır. Şu anda {$totalQuantity} adet girdin."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'materials.*.quantity.min' => 'Adet en az 1 olmalıdır.',
            'materials.*.unit_price.min' => 'Adet fiyatı en az 0,05 TL olmalıdır.',
            'materials.*.unit_price.max' => 'Adet fiyatı 1 TL üzerinde olamaz.',
            'materials.*.type.distinct' => 'Aynı malzeme bir ilana yalnızca bir kez eklenebilir.',
            'packaging_condition_confirmed.accepted' => 'Ambalajların DOA iade koşullarına uygun olduğunu onaylamalısın.',
            'address_id.exists' => 'Seçilen kayıtlı adres bulunamadı.',
            'exact_address.required_without' => 'Teslim alınacak açık adresi yazmalısın.',
            'photos.max' => 'Bir ilana en fazla 5 fotoğraf eklenebilir.',
        ];
    }
}