<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingDetailResource extends JsonResource
{
    use \App\Traits\ConvertsCurrency;

    public function toArray(Request $request): array
    {
        $originalCurrency = $this->currency ?? 'EUR';
        $currency = app()->bound('user.currency') ? app('user.currency') : $originalCurrency;
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'vendor_id' => $this->vendor_id,
            'booking_id' => $this->booking_id,
            'product_booking_id' => $this->product_booking_id,
            'product_confirmation_code' => $this->product_confirmation_code,
            'provider_type' => $this->provider_type,
            'status' => $this->status,
            'type' => $this->type,
            'title' => $this->title,
            'price' => $this->price !== null ? $this->formatPrice((float) $this->price, $originalCurrency) : null,
            'currency' => $this->price !== null ? $currency : $this->currency,
            'date' => optional($this->date)->toDateString(),
            'start_time' => $this->start_time,
            'quantity' => $this->quantity,
        ];
    }
}
