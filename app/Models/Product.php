<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasMediaUrls;
use App\Services\Sync\PriceCalculator;

class Product extends Model
{
    use HasMediaUrls;
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'product_api_id',
        'reference_code',
        'slug',
        'platform',
        'provider',
        'defaultRateId',
        'vendor_id',
        'category_id',
        'category_position',
        'barcode_type',
        'language',
        'title',
        'short_introduction',
        'description',
        'full_description',
        'thumbnail',
        'highlights_summary',
        'status',
        'is_top',
        'is_draft',
        'published',
        'wizard_step',
        'auto_content_text',
        'content_mode',
        'product_category',
        'activityType',
        'scheduleType',
        'bookingType',
        'difficulty',
        'privateActivity',
        'private_tour',
        'time_zone',
        'timezone_offset',
        'duration',
        'durationType',
        'durationMinutes',
        'durationHours',
        'durationDays',
        'durationWeeks',
        'durationText',
        'cutoffType',
        'cutoffReferenceHour',
        'cutoffReferenceMinute',
        'bookingCutoffMinutes',
        'bookingCutoffHours',
        'bookingCutoffDays',
        'bookingCutoffWeeks',
        'default_cut_off_time',
        'capacityType',
        'passCapacity',
        'passesAvailable',
        'meetingType',
        'meeting_point_address',
        'meeting_point_coordinates',
        'meeting_point_description',
        'arrival_time',
        'pickup_service',
        'pickupService',
        'pickup_info',
        'drop_off_type',
        'dropoffService',
        'has_transportation',
        'travels_to_different_city',
        'transportation_items',
        'transportation_type',
        'reservation_system',
        'pricing_type',
        'pricing_configuration',
        'options_configuration',
        'validation_types',
        'allowCustomizedBookings',
        'original_price',
        'from_price',
        'default_currency',
        'is_instant_confirmation',
        'is_cancellable',
        'guide_type',
        'food_included',
        'food_configuration',
        'reviewRating',
        'reviewCount',
        'copyright_acknowledged',
        'dressCode',
        'minAge',
        'passportRequired',
        'pet_friendly_policy',
        'not_suitable_for',
        'not_allowed',
        'mandatory_items',
        'excluded',
        'included',
        'know_before_you_go',
        'custom_info_before_booking',
        'other_info_before_activity',
        'what_to_expect',
        'terms_and_conditions',
        'operating_hours',
        'things_to_note',
        'how_to_use',
        'cancellation_notes',
        'address_line',
        'postal_code',
        'merchant_name',
        'activityCategories',
        'activityAttributes',
        'highlights',
        'inclusions',
        'exclusions',
        'itinerary',
        'guidanceTypes',
        'startTimes',
        'agendaItems',
        'rates',
        'pricingCategories',
        'startPoints',
        'bookableExtras',
        'image_gallery',
        'video_gallery',
        'booking_questions_data',
        'passenger_fields_data',
        'contact_fields_data',
        'raw_response',
        'raw_response_saved_at',
        'last_synced_at',
        'status',
    ];

    protected $casts = [
        'published' => 'boolean',
        'category_position' => 'integer',
        'is_top' => 'boolean',
        'is_draft' => 'boolean',
        'allowCustomizedBookings' => 'boolean',
        'passportRequired' => 'boolean',
        'privateActivity' => 'boolean',
        'has_transportation' => 'boolean',
        'travels_to_different_city' => 'boolean',
        'pet_friendly_policy' => 'boolean',
        'food_included' => 'boolean',
        'copyright_acknowledged' => 'boolean',
        'is_instant_confirmation' => 'boolean',
        'is_cancellable' => 'boolean',
        'wizard_step' => 'integer',
        'original_price' => 'decimal:2',
        'from_price' => 'decimal:2',
        'reviewRating' => 'decimal:1',
        'excluded' => 'array',
        'included' => 'array',
        'know_before_you_go' => 'array',
        'activityCategories' => 'array',
        'activityAttributes' => 'array',
        'highlights' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'itinerary' => 'array',
        'guidanceTypes' => 'array',
        'startTimes' => 'array',
        'agendaItems' => 'array',
        'rates' => 'array',
        'pricingCategories' => 'array',
        'startPoints' => 'array',
        'bookableExtras' => 'array',
        'options_configuration' => 'array',
        'pricing_configuration' => 'array',
        'validation_types' => 'array',
        'not_suitable_for' => 'array',
        'not_allowed' => 'array',
        'mandatory_items' => 'array',
        'food_configuration' => 'array',
        'transportation_items' => 'array',
        'operating_hours' => 'array',
        'things_to_note' => 'array',
        'how_to_use' => 'array',
        'cancellation_notes' => 'array',
        'image_gallery' => 'array',
        'video_gallery' => 'array',
        'booking_questions_data' => 'array',
        'passenger_fields_data' => 'array',
        'contact_fields_data' => 'array',
        'raw_response' => 'array',
        'raw_response_saved_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'meeting_point_coordinates' => 'array',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'raw_response',           // Massive JSON blob — never auto-serialize
        'raw_response_saved_at',
        'image_gallery',          // Deprecated JSON — use productImages relation
        'video_gallery',          // Deprecated JSON — use productVideos relation
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: products with active availability.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
            ->where('published', true)
            ->whereHas(
                'availabilities',
                fn($q) => $q
                    ->where('is_active', true)
                    ->where('sold_out', false)
                    ->where('available_date', '>=', now()->toDateString())
            );
    }

    /**
     * Check if product data is stale (older than given hours).
     */
    public function isStale(int $hours = 3): bool
    {
        if ($this->last_synced_at === null) {
            return true;
        }
        return $this->last_synced_at->diffInHours(now()) >= $hours;
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    /**
     * All bookable options (Bokun rates / GlobalTix activityOptions).
     */
    public function options()
    {
        return $this->hasMany(ProductOption::class, 'product_id')
            ->orderByDesc('is_default')
            ->orderBy('sort_order');
    }

    /**
     * Default option.
     */
    public function defaultOption()
    {
        return $this->hasOne(ProductOption::class, 'product_id')
            ->where('is_default', true);
    }

    public function location()
    {
        return $this->hasOne(Location::class, 'product_id')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'product_id')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function price()
    {
        return $this->hasOne(Price::class, 'product_id')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('valid_from')
            ->orderBy('id');
    }

    public function prices()
    {
        return $this->hasMany(Price::class, 'product_id')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('valid_from')
            ->orderBy('id');
    }

    public function cancellationPolicies()
    {
        return $this->hasMany(CancellationPolicy::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function bookings()
    {
        return $this->hasMany(BookingInfo::class, 'product_id');
    }

    /**
     * All images for this product (dedicated table).
     */
    public function productImages()
    {
        return $this->hasMany(ProductImage::class, 'product_id')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Primary image for this product.
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class, 'product_id')
            ->where('is_primary', true);
    }

    /**
     * All videos for this product (dedicated table).
     */
    public function productVideos()
    {
        return $this->hasMany(ProductVideo::class, 'product_id')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function keywords()
    {
        return $this->hasMany(Keyword::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function hotelKeywords()
    {
        return $this->hasMany(Keyword::class, 'product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Feature types assigned to this product (many-to-many).
     */
    public function featureTypes()
    {
        return $this->belongsToMany(FeatureType::class, 'feature_type_product', 'product_id', 'feature_type_id')
            ->withPivot('position')
            ->orderByPivot('position')
            ->withTimestamps();
    }

    public function availabilities()
    {
        return $this->hasMany(AvailabilityCheck::class, 'product_id')
            ->orderBy('available_date')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    // ─── Computed Properties / Legacy Accessors ─────────────────────────

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: fn(?string $value) => $this->resolveMediaUrl($value),
        );
    }

    public function getImagesAttribute(): Collection
    {
        $rows = $this->relationLoaded('productImages')
            ? $this->productImages
            : $this->productImages()->ordered()->get();

        if ($rows->isNotEmpty()) {
            return $rows
                ->map(function (ProductImage $image) {
                    return (object) [
                        'id' => $image->id,
                        'original' => $image->original_url,
                        'large' => $image->large_url ?? $image->original_url,
                        'preview' => $image->preview_url ?? $image->large_url ?? $image->original_url,
                        'alt_text' => $image->alt_text,
                        'external_id' => $image->external_id,
                        'mime_type' => $image->mime_type,
                        'file_size' => $image->file_size,
                        'extension' => $image->extension,
                        'provider_path' => $image->provider_path,
                        'sort_order' => $image->sort_order,
                        'meta' => $image->metadata,
                        'raw_original' => $image->provider_path ?? $image->original_url,
                    ];
                })
                ->values();
        }

        return collect($this->image_gallery ?? [])
            ->filter(fn($image) => is_array($image))
            ->map(function (array $image) {
                return (object) [
                    'id' => $image['id'] ?? null,
                    'original' => $this->resolveMediaUrl($image['original'] ?? null),
                    'large' => $this->resolveMediaUrl($image['large'] ?? $image['original'] ?? null),
                    'preview' => $this->resolveMediaUrl($image['preview'] ?? $image['large'] ?? $image['original'] ?? null),
                    'alt_text' => $image['alt_text'] ?? $image['title'] ?? null,
                    'external_id' => $image['external_id'] ?? $image['id'] ?? null,
                    'mime_type' => $image['mime_type'] ?? null,
                    'file_size' => $image['file_size'] ?? null,
                    'extension' => $image['extension'] ?? null,
                    'provider_path' => $image['provider_path'] ?? null,
                    'sort_order' => $image['sort_order'] ?? 0,
                    'meta' => $image['meta'] ?? null,
                    'raw_original' => $image['original'] ?? null,
                ];
            })
            ->sortBy('sort_order')
            ->values();
    }

    public function getVideosAttribute(): Collection
    {
        $rows = $this->relationLoaded('productVideos')
            ? $this->productVideos
            : $this->productVideos()->ordered()->get();

        if ($rows->isNotEmpty()) {
            return $rows
                ->map(function (ProductVideo $video) {
                    return [
                        'id' => $video->id,
                        'sourceUrl' => $video->source_url,
                        'thumbnailUrl' => $video->thumbnail_url,
                        'previewUrl' => $video->preview_url,
                        'html' => $video->embed_html,
                        'providerName' => $video->provider_name,
                        'title' => $video->title,
                        'description' => $video->description,
                        'duration' => $video->duration,
                        'external_id' => $video->external_id,
                        'sort_order' => $video->sort_order,
                    ];
                })
                ->values();
        }

        return collect($this->video_gallery ?? [])
            ->filter(fn($video) => is_array($video))
            ->map(function (array $video) {
                $video['sourceUrl'] = $this->resolveMediaUrl($video['sourceUrl'] ?? null);
                $video['thumbnailUrl'] = $this->resolveMediaUrl($video['thumbnailUrl'] ?? null);
                $video['previewUrl'] = $this->resolveMediaUrl($video['previewUrl'] ?? null);

                return $video;
            })
            ->values();
    }

    public function getBookingQuestionsAttribute(): Collection
    {
        return collect($this->booking_questions_data ?? [])->filter(fn($row) => is_array($row))->values();
    }

    public function getPassengerFieldsAttribute(): Collection
    {
        return collect($this->passenger_fields_data ?? [])->filter(fn($row) => is_array($row))->values();
    }

    public function getHotelContactFieldsAttribute(): Collection
    {
        return collect($this->contact_fields_data ?? [])->filter(fn($row) => is_array($row))->values();
    }

    public function getRawResponseAttribute($value): ?object
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($value) || $value === []) {
            return null;
        }

        return (object) [
            'provider' => $this->provider,
            'product_api_id' => $this->product_api_id,
            'raw_data' => $value,
        ];
    }

    /**
     * Dynamically resolve display from_price and original_price following Adult -> Child -> Lowest hierarchy.
     */
    public function getDisplayPrices(): array
    {
        $rawFromPrice = isset($this->attributes['from_price']) && $this->attributes['from_price'] !== null && $this->attributes['from_price'] !== ''
            ? (float) $this->attributes['from_price']
            : null;

        $rawOriginalPrice = isset($this->attributes['original_price']) && $this->attributes['original_price'] !== null && $this->attributes['original_price'] !== ''
            ? (float) $this->attributes['original_price']
            : null;

        // 1. Try resolving directly from raw_response if available (most accurate real-time snapshot)
        $raw = is_string($this->getRawOriginal('raw_response'))
            ? json_decode($this->getRawOriginal('raw_response'), true)
            : (array) ($this->raw_response->raw_data ?? $this->raw_response ?? []);

        if (is_array($raw) && !empty($raw)) {
            $rawOptions = data_get($raw, 'data.activityOptions.data')
                ?? data_get($raw, 'data.activityOptions')
                ?? data_get($raw, 'activityOptions.data')
                ?? data_get($raw, 'activityOptions')
                ?? data_get($raw, 'raw_data.data.activityOptions.data')
                ?? data_get($raw, 'raw_data.data.activityOptions')
                ?? data_get($raw, 'raw_data.activityOptions.data')
                ?? data_get($raw, 'raw_data.activityOptions')
                ?? [];

            $ticketCandidates = [];
            if (is_array($rawOptions)) {
                foreach ($rawOptions as $opt) {
                    if (!is_array($opt)) continue;
                    foreach (($opt['ticketType'] ?? []) as $ticket) {
                        if (!is_array($ticket)) continue;
                        $ticketCandidates[] = [
                            'name'                    => $ticket['name'] ?? null,
                            'nameTranslated'          => $ticket['nameTranslated'] ?? null,
                            'category_code'           => $ticket['categoryCode'] ?? $ticket['category_code'] ?? null,
                            'selling_price'           => PriceCalculator::resolveGlobalTixSellingPrice($ticket, $this->default_currency ?? 'EUR'),
                            'original_price'          => PriceCalculator::resolveGlobalTixOriginalPrice($ticket, $this->default_currency ?? 'EUR'),
                            'recommendedSellingPrice' => $ticket['recommendedSellingPrice'] ?? null,
                            'originalPrice'           => $ticket['originalPrice'] ?? null,
                            'currency'                => $ticket['currency'] ?? $this->default_currency ?? 'EUR',
                        ];
                    }
                }
            }

            // Also check availability slots in raw_response
            $availData = data_get($raw, 'data.availability.data')
                ?? data_get($raw, 'availability.data')
                ?? data_get($raw, 'raw_data.data.availability.data')
                ?? data_get($raw, 'raw_data.availability.data')
                ?? [];

            if (is_array($availData)) {
                foreach ($availData as $avail) {
                    if (!is_array($avail)) continue;
                    $ticketCandidates[] = [
                        'name'                    => $avail['ticket_name'] ?? $avail['name'] ?? null,
                        'nameTranslated'          => $avail['nameTranslated'] ?? null,
                        'category_code'           => $avail['category_code'] ?? $avail['categoryCode'] ?? null,
                        'selling_price' => PriceCalculator::firstNumeric([
                            $avail['recommendedSellingPrice'] ?? null,
                            $avail['sellingPrice'] ?? null,
                            $avail['price'] ?? null,
                            $avail['recommendedMerchantSellingPrice'] ?? null,
                        ]),
                        'original_price' => PriceCalculator::firstNumeric([
                            $avail['originalPrice'] ?? null,
                            $avail['recommendedSellingPrice'] ?? null,
                            $avail['originalMerchantPrice'] ?? null,
                        ]),
                        'recommendedSellingPrice' => $avail['recommendedSellingPrice'] ?? null,
                        'originalPrice'           => $avail['originalPrice'] ?? null,
                        'currency'                => $avail['currency'] ?? $this->default_currency ?? 'EUR',
                    ];
                }
            }

            if (!empty($ticketCandidates)) {
                $resolved = PriceCalculator::resolveProductDisplayPrices($ticketCandidates, $rawFromPrice, $rawOriginalPrice);
                if ($resolved['from_price'] !== null && $resolved['from_price'] > 0) {
                    return $resolved;
                }
            }
        }

        // 2. Check loaded or DB option pricing categories
        $categories = collect();
        if ($this->relationLoaded('options')) {
            $categories = $this->options->flatMap(function ($option) {
                return $option->relationLoaded('pricingCategories')
                    ? $option->pricingCategories
                    : $option->pricingCategories()->get();
            });
        }

        if ($categories->isEmpty()) {
            $categories = \App\Models\OptionPricingCategory::whereIn(
                'product_option_id',
                \App\Models\ProductOption::where('product_id', $this->id)->select('id')
            )->get();
        }

        if ($categories->isNotEmpty()) {
            $resolved = PriceCalculator::resolveProductDisplayPrices($categories, $rawFromPrice, $rawOriginalPrice);
            if ($resolved['from_price'] !== null && $resolved['from_price'] > 0) {
                return $resolved;
            }
        }

        // 3. Fallback: check prices table
        $prices = $this->relationLoaded('prices') ? $this->prices : $this->prices()->get();
        if ($prices->isNotEmpty()) {
            $resolved = PriceCalculator::resolveProductDisplayPrices($prices, $rawFromPrice, $rawOriginalPrice);
            if ($resolved['from_price'] !== null && $resolved['from_price'] > 0) {
                return $resolved;
            }
        }

        // 4. Resolve fallback with price sanity
        return PriceCalculator::resolveProductDisplayPrices([], $rawFromPrice, $rawOriginalPrice);
    }

    public function getFromPriceAttribute($value): ?float
    {
        $prices = $this->getDisplayPrices();
        if ($prices['from_price'] !== null && $prices['from_price'] > 0) {
            return (float) $prices['from_price'];
        }
        if ($value !== null && is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }

    public function getOriginalPriceAttribute($value): ?float
    {
        $prices = $this->getDisplayPrices();
        if ($prices['original_price'] !== null && $prices['original_price'] > 0) {
            return (float) $prices['original_price'];
        }
        if ($value !== null && is_numeric($value)) {
            return (float) $value;
        }
        return null;
    }

    // Removed normalizeMediaUrl in favor of resolveMediaUrl from HasMediaUrls trait

    // ─── Cache Invalidation ───────────────────────────────────────────

    /**
     * Boot the model and register event listeners for cache invalidation.
     *
     * Clears the pre-calculated "category:stats:{id}" cache keys whenever a
     * product in that category is created, updated, or deleted. This ensures
     * the SearchController always uses fresh data without waiting for the
     * hourly PrecalculateCategoryStats job to refresh.
     */
}
