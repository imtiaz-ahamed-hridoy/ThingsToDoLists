<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');
        if ($productId instanceof \App\Models\Product) {
            $productId = $productId->id;
        }

        return [
            // Core
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration' => 'nullable|string|max:100',
            'durationType' => 'nullable|string|max:50',
            'difficulty' => 'nullable|string|max:50',
            'pickup_service' => 'nullable|string|max:255',
            'private_tour' => 'nullable|string|max:255',
            'published' => 'boolean',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'reviewRating' => 'nullable|integer|min:0|max:5',
            'activityType' => 'nullable|string|max:100',
            'bookingType' => 'nullable|string|max:100',
            'cutoffType' => 'nullable|string|max:100',
            'cutoffReferenceHour' => 'nullable|integer|min:0',
            'cutoffReferenceMinute' => 'nullable|integer|min:0',
            'capacityType' => 'nullable|string|max:100',
            'passCapacity' => 'nullable|integer|min:0',
            'passesAvailable' => 'nullable|integer|min:0',
            'allowCustomizedBookings' => 'boolean',
            'passportRequired' => 'boolean',
            'status' => 'nullable|in:ACTIVE,INACTIVE',

            // Vendor
            'use_existing_vendor' => 'required|boolean',
            'vendor_id' => 'required_if:use_existing_vendor,1|nullable|exists:vendors,id',
            'vendor.name' => 'required_if:use_existing_vendor,0|nullable|string|max:255',
            'vendor.currency' => 'nullable|string|max:10',
            'vendor.rating' => 'nullable|numeric|min:0|max:5',
            'vendor.reviews' => 'nullable|integer|min:0',
            'vendor.link' => 'nullable|url',

            // Category
            'use_existing_category' => 'required|boolean',
            'category_id' => 'required_if:use_existing_category,1|nullable|exists:categories,id',
            'category.name' => 'required_if:use_existing_category,0|nullable|string|max:255',
            'category.slug' => 'required_if:use_existing_category,0|nullable|string|max:255',
            'category.type' => 'nullable|string|max:255',

            // Barcode
            'barcode_type' => 'nullable|string|max:255',

            // Location
            'location.city' => 'nullable|string|max:255',
            'location.country' => 'nullable|string|max:255',
            'location.countryCode' => 'nullable|string|max:10',
            'location.coordinates' => 'nullable|json',

            // JSON fields
            'activityCategories' => 'nullable|array',
            'activityAttributes' => 'nullable|array',
            'highlights' => 'nullable|array',
            'inclusions' => 'nullable|array',
            'exclusions' => 'nullable|array',
            'itinerary' => 'nullable',

            // Keywords
            'keywords' => 'nullable|array',
            'keywords.*' => 'nullable|string|max:255',

            // Pricing
            'price.amount' => 'required|numeric|min:0',
            'price.defaultCurrency' => 'required|string|max:10',
            'price.from' => 'nullable|date',
            'price.to' => 'nullable|date|after_or_equal:price.from',
            'price.isPriceConverted' => 'boolean',
            'price.rates' => 'nullable|json',

            // Cancellation
            'cancellation_policies' => 'nullable|array',
            'cancellation_policies.*.policy' => 'required|string',
            'cancellation_policies.*.policyType' => 'nullable|string',
            'cancellation_policies.*.tax' => 'nullable|string',
            'cancellation_policies.*.details' => 'nullable|array',
            'cancellation_policies.*.details.*.cutoffHours' => 'nullable|integer',
            'cancellation_policies.*.details.*.charge' => 'nullable|integer',
            'cancellation_policies.*.details.*.chargeType' => 'nullable|in:percentage,fixed',

            // Passenger & Hotel Fields
            'passenger_fields' => 'nullable|json',
            'hotel_contact_fields' => 'nullable|json',

            // Booking questions
            'booking_questions' => 'nullable|array',
            'booking_questions.*.label' => 'required|string',
            'booking_questions.*.dataType' => 'required|string',
            'booking_questions.*.options' => 'nullable',
            'booking_questions.*.flags' => 'nullable',

            // Media
            'thumbnail' => 'nullable|image',
            'images' => 'nullable|array',
            'images.*.original' => 'nullable|image|max:10240',
            'images.*.preview' => 'nullable|image|max:10240',
            'deleted_images' => 'nullable|string',

            // Videos
            'video_files' => 'nullable|array',
            'video_files.*' => 'nullable|file|mimes:mp4,webm,mov,ogg,avi,mkv,flv,wmv,m4v,3gp,qt|max:204800',
            'remove_videos' => 'nullable|array',
            'remove_videos.*' => 'nullable|integer|exists:product_videos,id',
        ];
    }
}
