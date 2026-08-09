<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'provinceId' => $this->province_id,
            'provinceName' => $this->province?->name,
            'districtId' => $this->district_id,
            'districtName' => $this->district?->name,
            'neighborhood' => $this->neighborhood,
            'publicArea' => $this->public_area,
            'fullAddress' => $this->full_address,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'deliveryNotes' => $this->delivery_notes,
            'isDefault' => $this->is_default,
            'activeListingsCount' => (int) ($this->active_listings_count ?? 0),
        ];
    }
}
