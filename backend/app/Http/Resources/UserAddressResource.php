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
            'publicArea' => $this->public_area,
            'fullAddress' => $this->full_address,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'deliveryNotes' => $this->delivery_notes,
            'isDefault' => $this->is_default,
        ];
    }
}