<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingInfoResource extends JsonResource
{
    use \App\Traits\ConvertsCurrency;

    public function toArray(Request $request): array
    {
        $originalCurrency = $this->currency ?? 'EUR';
        $currency = app()->bound('user.currency') ? app('user.currency') : $originalCurrency;
        // If this is an event row (audit trail), return event-focused fields
        if ($this->event_type !== null) {
            return [
                'id' => $this->id,
                'event_type' => $this->event_type,
                'old_status' => $this->old_status,
                'new_status' => $this->new_status,
                'actor_type' => $this->actor_type,
                'actor_id' => $this->actor_id,
                'message' => $this->message,
                'payload' => $this->event_payload,
                'created_at' => optional($this->created_at)->toIso8601String(),
            ];
        }

        // Enrich GlobalTix detail rows with real ticket name and price if needed
        $title = $this->title;
        $price = $this->price !== null ? (float) $this->price : null;

        if (in_array($this->provider_type, ['globaltix', 'bokun']) && $this->product_booking_id) {
            $needsEnrichment = ($price === null || $price == 0)
                || str_starts_with((string) $title, 'GlobalTix Ticket #')
                || str_starts_with((string) $title, 'Bokun Ticket #');

            if ($needsEnrichment) {
                $category = \App\Models\OptionPricingCategory::where('provider', $this->provider_type)
                    ->where('external_id', (string) $this->product_booking_id)
                    ->first();

                if ($category) {
                    if ((str_starts_with((string) $title, 'GlobalTix Ticket #') || str_starts_with((string) $title, 'Bokun Ticket #')) && $category->category_label) {
                        $title = (string) $category->category_label;
                    }
                    if (($price === null || $price == 0) && $category->selling_price > 0) {
                        $price = (float) $category->selling_price;
                    }
                } else {
                    $option = \App\Models\ProductOption::where('provider', $this->provider_type)
                        ->where('external_id', (string) $this->product_booking_id)
                        ->first();
                        
                    if ($option) {
                        if (str_starts_with((string) $title, 'GlobalTix Ticket #') || str_starts_with((string) $title, 'Bokun Ticket #')) {
                            $title = (string) ($option->name ?: $title);
                        }
                        $firstCat = $option->pricingCategories()->first();
                        if (($price === null || $price == 0) && $firstCat && $firstCat->selling_price > 0) {
                            $price = (float) $firstCat->selling_price;
                        }
                    } else {
                        $prod = \App\Models\Product::where('provider', $this->provider_type)
                            ->where('product_api_id', (string) $this->product_booking_id)
                            ->first();
                            
                        if ($prod) {
                            if (str_starts_with((string) $title, 'GlobalTix Ticket #') || str_starts_with((string) $title, 'Bokun Ticket #')) {
                                $title = (string) ($prod->title ?: $title);
                            }
                            if (($price === null || $price == 0) && $prod->from_price > 0) {
                                $price = (float) $prod->from_price;
                            }
                        }
                    }
                }

                if ($this->title !== $title || $this->price != $price) {
                    $this->resource->forceFill(array_filter([
                        'title' => $title,
                        'price' => $price,
                    ]))->saveQuietly();
                }
            }
        }

        // For detail rows (product bookings, tickets, line-items)
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'product_booking_id' => $this->product_booking_id,
            'product_confirmation_code' => $this->product_confirmation_code,
            'provider_type' => $this->provider_type,
            'status' => $this->status,
            'type' => $this->type,
            'title' => $title,
            // GlobalTix API returns prices in EUR \u2014 use rawPrice() (pass-through, no conversion).
            // Other providers use formatPrice() to apply local exchange-rate conversion.
            'price' => $price !== null
                ? (in_array($this->provider_type, ['globaltix'])
                    ? $this->rawPrice((float) $price)
                    : $this->formatPrice((float) $price, $originalCurrency))
                : null,
            'currency' => $price !== null
                ? (in_array($this->provider_type, ['globaltix']) ? $originalCurrency : $currency)
                : $this->currency,
            'date' => optional($this->date)->toDateString(),
            'start_time' => $this->start_time,
            'quantity' => $this->quantity,
            'e_ticket_url' => $this->e_ticket_url,
            'metadata' => $this->provider_raw_data,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
