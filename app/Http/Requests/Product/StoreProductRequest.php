<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Category
            'use_existing_category' => 'required|boolean',
            'category_id' => 'required_if:use_existing_category,1|nullable|exists:categories,id',
            'category.name' => 'required_if:use_existing_category,0|nullable|string|max:255',
            'category.slug' => 'required_if:use_existing_category,0|nullable|string|unique:categories,slug',
            'category.type' => 'nullable|string|max:255',

            // Vendor
            'use_existing_vendor' => 'required|boolean',
            'vendor_id' => 'required_if:use_existing_vendor,1|nullable|exists:vendors,id',
            'vendor.name' => 'required_if:use_existing_vendor,0|nullable|string|max:255',
            'vendor.currency' => 'nullable|string|max:10',
            'vendor.rating' => 'nullable|numeric|min:0|max:5',
            'vendor.reviews' => 'nullable|integer|min:0',
            'vendor.link' => 'nullable|url',

            // Product
            'provider' => 'nullable|string|max:50',
            'platform' => 'nullable|string|max:50',
            'product_api_id' => 'nullable|string|max:255',
            'reference_code' => 'nullable|string|max:100',
            'barcode_type' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'product_category' => 'nullable|string|max:100',
            'title' => 'required|string|max:1000',
            'description' => 'nullable|string',
            'full_description' => 'nullable|string',
            'short_introduction' => 'nullable|string|max:255',
            'highlights_summary' => 'nullable|string',
            'content_mode' => 'nullable|string|max:50',
            'thumbnail' => 'nullable|image',
            'duration' => 'nullable|string|max:255',
            'durationType' => 'nullable|string|max:50',
            'durationMinutes' => 'nullable|integer|min:0',
            'durationHours' => 'nullable|integer|min:0',
            'durationDays' => 'nullable|integer|min:0',
            'durationWeeks' => 'nullable|integer|min:0',
            'durationText' => 'nullable|string|max:255',
            'difficulty' => 'nullable|string|max:50',
            'pickup_service' => 'nullable|string|max:255',
            'private_tour' => 'nullable|string|max:255',
            'privateActivity' => 'nullable|boolean',
            'guide_type' => 'nullable|string|max:255',
            'max_group_size' => 'nullable|integer|min:0',
            'online_reservation' => 'nullable|boolean',
            'is_instant_confirmation' => 'nullable|boolean',
            'is_cancellable' => 'nullable|boolean',
            'published' => 'required|boolean',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'reviewRating' => 'nullable|numeric|min:0|max:5',
            'reviewCount' => 'nullable|integer|min:0',
            'activityType' => 'nullable|string|max:100',
            'scheduleType' => 'nullable|string|max:100',
            'bookingType' => 'nullable|string|max:100',
            'cutoffType' => 'nullable|string|max:100',
            'cutoffReferenceHour' => 'nullable|integer|min:0',
            'cutoffReferenceMinute' => 'nullable|integer|min:0',
            'bookingCutoffMinutes' => 'nullable|integer|min:0',
            'bookingCutoffHours' => 'nullable|integer|min:0',
            'bookingCutoffDays' => 'nullable|integer|min:0',
            'bookingCutoffWeeks' => 'nullable|integer|min:0',
            'capacityType' => 'nullable|string|max:100',
            'passCapacity' => 'nullable|integer|min:0',
            'passesAvailable' => 'nullable|integer|min:0',
            'allowCustomizedBookings' => 'required|boolean',
            'passportRequired' => 'required|boolean',
            'status' => 'nullable|in:draft,active,inactive',

            // Arrays
            'activityAttributes' => 'nullable|array',
            'activityCategories' => 'nullable|array',
            'highlights' => 'nullable|array',
            'inclusions' => 'nullable|array',
            'exclusions' => 'nullable|array',
            'itinerary' => 'nullable',

            // Location
            'location.city' => 'required|string',
            'location.countryCode' => 'required|string',
            'location.country' => 'required|string',
            'location.coordinates' => 'nullable|json',
            'location.address' => 'nullable|string',
            'location.postal_code' => 'nullable|string|max:20',
            'location.city_id' => 'nullable|string|max:100',

            // Pricing
            'price.defaultCurrency' => 'required|string|max:10',
            'price.isPriceConverted' => 'nullable|boolean',
            'price.from' => 'nullable|date',
            'price.to' => 'nullable|date|after_or_equal:price.from',
            'price.amount' => 'required|numeric|min:0',
            'price.rates' => 'nullable|json',

            // Cancellation
            'cancellation_policies' => 'nullable|array',
            'cancellation_policies.*.policy' => 'required|string',
            'cancellation_policies.*.policyType' => 'nullable|string',
            'cancellation_policies.*.tax' => 'nullable|string',
            'cancellation_policies.*.details' => 'nullable|array',
            'cancellation_policies.*.details.*.cutoffHours' => 'nullable|integer|min:0',
            'cancellation_policies.*.details.*.charge' => 'nullable|integer|min:0',
            'cancellation_policies.*.details.*.chargeType' => 'nullable|in:percentage,fixed',
            'cancellation_policies.*.details.*.percentage' => 'nullable|string',

            // Booking questions
            'booking_questions' => 'nullable|array',
            'booking_questions.*.personalData' => 'nullable|boolean',
            'booking_questions.*.required' => 'nullable|boolean',
            'booking_questions.*.selectFromOptions' => 'nullable|boolean',
            'booking_questions.*.selectMultiple' => 'nullable|boolean',
            'booking_questions.*.requiredBeforeDeparture' => 'nullable|boolean',
            'booking_questions.*.questionCode' => 'nullable|string|max:255',
            'booking_questions.*.label' => 'nullable|string',
            'booking_questions.*.help' => 'nullable|string',
            'booking_questions.*.placeholder' => 'nullable|string|max:255',
            'booking_questions.*.dataType' => 'nullable|in:string,number,date,email,phone,boolean',
            'booking_questions.*.context' => 'nullable|string|max:255',
            'booking_questions.*.pricingCategoryTriggerSelection' => 'nullable|string|max:255',
            'booking_questions.*.rateTriggerSelection' => 'nullable|string|max:255',
            'booking_questions.*.extraTriggerSelection' => 'nullable|string|max:255',
            'booking_questions.*.options' => 'nullable',
            'booking_questions.*.flags' => 'nullable',

            // JSON snapshots
            'passenger_fields' => 'nullable|json',
            'hotel_contact_fields' => 'nullable|json',

            // Keywords
            'keywords' => 'nullable|json',

            // Images
            'images' => 'nullable|array',
            'images.*.original' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'images.*.large' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'images.*.preview' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',

            // Videos
            'video_files' => 'nullable|array',
            'video_files.*' => 'nullable|file|mimes:mp4,webm,mov,ogg,avi,mkv,flv,wmv,m4v,3gp,qt|max:204800',
        ];
    }
}
