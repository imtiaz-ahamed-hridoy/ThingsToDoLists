<?php

namespace App\Services;

use App\Models\{
    CancellationPolicy, Categories, Image as LegacyImage, Keyword,
    Location, OptionPricingCategory, Product, ProductImage,
    ProductOption, ProductVideo, Price, TieredPrice, Vendor
};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    // ─────────────────────────────────────────────────────────────
    //  PUBLIC API
    // ─────────────────────────────────────────────────────────────

    public function create(array $data, array $images, array $videoFiles): Product
    {
        $provider = strtolower((string) ($data['provider'] ?? 'supplier'));
        $platform = strtolower((string) ($data['platform'] ?? $provider));
        $published = (bool) ($data['published'] ?? false);
        $status = $data['status'] ?? ($published ? 'active' : 'draft');
        $priceConfig = $this->decodeJsonPayload($data['price']['rates'] ?? null, []);
        $basePrice = $this->normalizeMoney($data['price']['amount'] ?? 0);
        $currency = strtoupper((string) ($data['price']['defaultCurrency'] ?? 'EUR'));

        $product = null;

        DB::transaction(function () use ($data, $provider, $platform, $published, $status, $priceConfig, $basePrice, $currency, &$product) {
            $vendorId = $this->resolveVendorId($data, $provider);
            $categoryId = $this->resolveCategoryId($data);

            $product = Product::create([
                'vendor_id'     => $vendorId,
                'category_id'   => $categoryId,
                'provider'      => $provider,
                'platform'      => $platform,
                'product_api_id' => $data['product_api_id'] ?? null,
                'reference_code' => $data['reference_code'] ?? null,
                'barcode_type'  => trim((string) ($data['barcode_type'] ?? '')) ?: null,
                'language'      => strtolower((string) ($data['language'] ?? 'en')),
                'product_category' => $data['product_category'] ?? null,
                'title'         => $data['title'],
                'description'   => $data['description'] ?? null,
                'full_description' => $data['full_description'] ?? null,
                'short_introduction' => $data['short_introduction'] ?? null,
                'highlights_summary' => $data['highlights_summary'] ?? null,
                'content_mode'  => $data['content_mode'] ?? 'manual',
                'duration'      => $data['duration'] ?? null,
                'durationType'  => $data['durationType'] ?? null,
                'durationMinutes' => $data['durationMinutes'] ?? null,
                'durationHours' => $data['durationHours'] ?? null,
                'durationDays'  => $data['durationDays'] ?? null,
                'durationWeeks' => $data['durationWeeks'] ?? null,
                'durationText'  => $data['durationText'] ?? null,
                'difficulty'    => $data['difficulty'] ?? null,
                'pickup_service' => $data['pickup_service'] ?? null,
                'private_tour'  => $data['private_tour'] ?? null,
                'privateActivity' => (bool) ($data['privateActivity'] ?? false),
                'guide_type'    => $data['guide_type'] ?? null,
                'max_group_size' => $data['max_group_size'] ?? null,
                'online_reservation' => (bool) ($data['online_reservation'] ?? true),
                'is_instant_confirmation' => (bool) ($data['is_instant_confirmation'] ?? false),
                'is_cancellable' => (bool) ($data['is_cancellable'] ?? false),
                'published'     => $published,
                'is_draft'      => $status !== 'active',
                'wizard_step'   => 0,
                'slug'          => $this->generateUniqueSlug($data['slug'] ?? $data['title']),
                'reviewRating'  => $data['reviewRating'] ?? null,
                'reviewCount'   => $data['reviewCount'] ?? 0,
                'activityType'  => $data['activityType'] ?? 'ACTIVITY',
                'scheduleType'  => $data['scheduleType'] ?? null,
                'bookingType'   => $data['bookingType'] ?? 'SHARED',
                'cutoffType'    => $data['cutoffType'] ?? null,
                'cutoffReferenceHour' => $data['cutoffReferenceHour'] ?? null,
                'cutoffReferenceMinute' => $data['cutoffReferenceMinute'] ?? null,
                'bookingCutoffMinutes' => $data['bookingCutoffMinutes'] ?? null,
                'bookingCutoffHours'   => $data['bookingCutoffHours'] ?? null,
                'bookingCutoffDays'    => $data['bookingCutoffDays'] ?? null,
                'bookingCutoffWeeks'   => $data['bookingCutoffWeeks'] ?? null,
                'capacityType'  => $data['capacityType'] ?? null,
                'passCapacity'  => $data['passCapacity'] ?? null,
                'passesAvailable' => $data['passesAvailable'] ?? null,
                'allowCustomizedBookings' => (bool) ($data['allowCustomizedBookings'] ?? false),
                'passportRequired' => (bool) ($data['passportRequired'] ?? false),
                'status'        => $status,
                'from_price'    => $basePrice,
                'original_price' => $basePrice,
                'default_currency' => $currency,
                'pricing_configuration' => $priceConfig,
                'booking_questions_data' => $this->normalizeBookingQuestions($data['booking_questions'] ?? []),
                'passenger_fields_data'  => $this->decodeJsonPayload($data['passenger_fields'] ?? null, []),
                'contact_fields_data'    => $this->decodeJsonPayload($data['hotel_contact_fields'] ?? null, []),
                'activityAttributes' => $this->normalizeStringList($data['activityAttributes'] ?? []),
                'activityCategories' => $data['activityCategories'] ?? [],
                'highlights'    => $this->normalizeStringList($data['highlights'] ?? []),
                'inclusions'    => $this->normalizeStringList($data['inclusions'] ?? []),
                'exclusions'    => $this->normalizeStringList($data['exclusions'] ?? []),
                'itinerary'     => $this->normalizeItinerary($data['itinerary'] ?? []),
            ]);

            // Thumbnail
            if (!empty($images['thumbnail'])) {
                $product->thumbnail = $this->imageUploadService->upload(
                    $images['thumbnail'], "products/{$product->id}/thumbnail"
                );
                $product->save();
            }

            // Location
            $coordinates = $this->decodeJsonPayload($data['location']['coordinates'] ?? null, []);
            Location::create([
                'product_id'   => $product->id,
                'city'         => $data['location']['city'],
                'countryCode'  => strtoupper($data['location']['countryCode']),
                'country'      => $data['location']['country'],
                'address'      => $data['location']['address'] ?? null,
                'postal_code'  => $data['location']['postal_code'] ?? null,
                'city_id'      => $data['location']['city_id'] ?? null,
                'latitude'     => $this->normalizeMoney($coordinates['latitude'] ?? null),
                'longitude'    => $this->normalizeMoney($coordinates['longitude'] ?? null),
                'coordinates'  => $coordinates ?: null,
                'location_type' => 'destination',
                'label'        => $data['location']['city'],
                'is_primary'   => true,
                'sort_order'   => 0,
            ]);

            // Pricing
            $pricingSummary = $this->syncManualPricing($product, $data['price'], $priceConfig);
            $product->update([
                'from_price'     => $pricingSummary['from_price'],
                'original_price' => $pricingSummary['original_price'],
                'default_currency' => $pricingSummary['currency'],
            ]);

            // Cancellation policies
            $this->syncCancellationPolicies($product, $data['cancellation_policies'] ?? [], $provider);

            // Keywords
            $this->syncKeywords($product, $this->decodeJsonPayload($data['keywords'] ?? null, []), $provider);

            // Images
            $primaryImageUrl = $this->storeProductImages($product, $images['product_images'] ?? [], $provider);

            // Videos
            $this->storeProductVideos($product, $videoFiles, $provider);

            if (!$product->thumbnail && $primaryImageUrl) {
                $product->thumbnail = $primaryImageUrl;
                $product->save();
            }
        });

        // After transaction: auto-create availability for manual/supplier products
        if (in_array($provider, ['manual', 'supplier'], true) && $product) {
            $this->createDefaultAvailability($product);
        }

        return $product;
    }

    private function createDefaultAvailability(Product $product): void
    {
        // Only create if no availability slot exists yet
        if (\App\Models\AvailabilityCheck::where('product_id', $product->id)->exists()) {
            return;
        }

        $productOption = $product->options()->first();

        \App\Models\AvailabilityCheck::create([
            'product_id'             => $product->id,
            'product_option_id'      => $productOption?->id,
            'provider'               => $product->provider,
            'available_date'         => now()->toDateString(),
            'start_time'             => '00:00:00',
            'end_time'               => '23:59:59',
            'capacity_total'         => 9999,
            'capacity_booked'        => 0,
            'capacity_reserved'      => 0,
            'unlimited_availability' => true,
            'is_active'              => true,
            'sold_out'               => false,
            'is_closed'              => false,
            'min_participants'       => 1,
            'max_participants'       => 9999,
        ]);
    }

    public function update(Product $product, array $data, array $files): Product
    {
        DB::transaction(function () use ($product, $data, $files) {
            // Vendor
            if (isset($data['use_existing_vendor'])) {
                if ((bool) $data['use_existing_vendor']) {
                    $product->vendor_id = $data['vendor_id'] ?? $product->vendor_id;
                } elseif (!empty($data['vendor']['name'])) {
                    $vendor = Vendor::updateOrCreate(
                        ['name' => $data['vendor']['name']],
                        $data['vendor']
                    );
                    $product->vendor_id = $vendor->id;
                }
            }

            // Category
            if (isset($data['use_existing_category'])) {
                if ((bool) $data['use_existing_category']) {
                    $product->category_id = $data['category_id'] ?? $product->category_id;
                } elseif (!empty($data['category']['slug']) || !empty($data['category']['name'])) {
                    $slug = !empty($data['category']['slug']) ? Str::slug($data['category']['slug']) : Str::slug($data['category']['name']);
                    $category = Categories::where('slug', $slug)->first();
                    $product->category_id = $category ? $category->id : null;
                }
            }

            // Thumbnail
            if (!empty($files['thumbnail'])) {
                $this->imageUploadService->delete($product->getRawOriginal('thumbnail'));
                $product->thumbnail = $this->imageUploadService->upload(
                    $files['thumbnail'], 'products'
                );
            }

            // Core fields
            $contentLists = $this->normalizeProductContentLists($data);
            $itinerary = $this->normalizeItinerary($data['itinerary'] ?? []);

            $product->fill([
                'title'       => $data['title'] ?? $product->title,
                'barcode_type' => $data['barcode_type'] ?? $product->barcode_type,
                'description' => $data['description'] ?? $product->description,
                'duration'    => $data['duration'] ?? $product->duration,
                'durationType' => $data['durationType'] ?? $product->durationType,
                'difficulty'  => $data['difficulty'] ?? $product->difficulty,
                'pickup_service' => $data['pickup_service'] ?? $product->pickup_service,
                'private_tour' => $data['private_tour'] ?? $product->private_tour,
                'published'   => (bool) ($data['published'] ?? $product->published),
                'slug'        => $data['slug'] ?? $product->slug,
                'reviewRating' => $data['reviewRating'] ?? $product->reviewRating,
                'activityType' => $data['activityType'] ?? $product->activityType,
                'bookingType' => $data['bookingType'] ?? $product->bookingType,
                'cutoffType'  => $data['cutoffType'] ?? $product->cutoffType,
                'cutoffReferenceHour' => $data['cutoffReferenceHour'] ?? $product->cutoffReferenceHour,
                'cutoffReferenceMinute' => $data['cutoffReferenceMinute'] ?? $product->cutoffReferenceMinute,
                'capacityType' => $data['capacityType'] ?? $product->capacityType,
                'passCapacity' => $data['passCapacity'] ?? $product->passCapacity,
                'passesAvailable' => $data['passesAvailable'] ?? $product->passesAvailable,
                'allowCustomizedBookings' => (bool) ($data['allowCustomizedBookings'] ?? $product->allowCustomizedBookings),
                'passportRequired' => (bool) ($data['passportRequired'] ?? $product->passportRequired),
                'status'      => $data['status'] ?? $product->status,
                'activityCategories' => $data['activityCategories'] ?? $product->activityCategories ?? [],
                'activityAttributes' => $contentLists['activityAttributes'],
                'highlights'  => $contentLists['highlights'],
                'inclusions'  => $contentLists['inclusions'],
                'exclusions'  => $contentLists['exclusions'],
                'itinerary'   => $itinerary,
            ]);
            $product->save();

            // Location
            if (!empty($data['location'])) {
                $product->location()->updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'city'        => $data['location']['city'] ?? null,
                        'country'     => $data['location']['country'] ?? null,
                        'countryCode' => $data['location']['countryCode'] ?? null,
                        'coordinates' => $this->decodeJsonPayload($data['location']['coordinates'] ?? null, []),
                    ]
                );
            }

            // Keywords
            if (array_key_exists('keywords', $data)) {
                Keyword::where('product_id', $product->id)->delete();
                $provider = strtolower((string) ($data['provider'] ?? $data['platform'] ?? $product->provider));
                foreach (($data['keywords'] ?? []) as $index => $keywordName) {
                    $name = trim((string) (is_string($keywordName) ? $keywordName : ''));
                    if ($name === '') continue;
                    Keyword::create([
                        'product_id' => $product->id,
                        'provider'   => $provider,
                        'name'       => $name,
                        'slug'       => Str::slug($name),
                        'type'       => 'tag',
                        'sort_order' => $index,
                        'meta'       => null,
                    ]);
                }
            }

            // Videos — delete removed
            if (!empty($data['remove_video_ids'])) {
                foreach ($data['remove_video_ids'] as $videoId) {
                    $video = ProductVideo::find($videoId);
                    if ($video && $video->product_id === $product->id) {
                        $rawPath = $video->getRawOriginal('source_url');
                        $this->imageUploadService->delete($rawPath);
                        $video->delete();
                    }
                }
            }

            // Videos — add new
            if (!empty($files['new_videos'])) {
                $provider = strtolower((string) ($data['provider'] ?? $data['platform'] ?? $product->provider ?? 'manual'));
                $existingCount = $product->productVideos()->count();
                foreach ($files['new_videos'] as $index => $file) {
                    if (!$file || !$file->isValid()) continue;
                    $relativePath = $this->imageUploadService->upload($file, "products/{$product->id}/videos");
                    ProductVideo::create([
                        'product_id'    => $product->id,
                        'provider'      => $provider,
                        'source_url'    => $relativePath,
                        'thumbnail_url' => $relativePath,
                        'preview_url'   => $relativePath,
                        'title'         => $file->getClientOriginalName(),
                        'provider_name' => 'Local Upload',
                        'mime_type'     => $file->getClientMimeType(),
                        'file_size'     => $file->getSize(),
                        'is_primary'    => $existingCount === 0 && $index === 0,
                        'is_active'     => true,
                        'sort_order'    => $existingCount + $index,
                        'metadata'      => ['source' => 'admin_product_update', 'original_filename' => $file->getClientOriginalName()],
                    ]);
                }
            }

            // Passenger fields
            if (array_key_exists('passenger_fields', $data)) {
                $product->passengerFields()->delete();
                foreach ($this->decodeJsonPayload($data['passenger_fields'] ?? [], []) as $pf) {
                    $product->passengerFields()->create([
                        'field' => $pf['field'] ?? '',
                        'required' => $pf['required'] ?? false,
                        'requiredBeforeDeparture' => $pf['requiredBeforeDeparture'] ?? false,
                    ]);
                }
            }

            // Hotel contact fields
            if (array_key_exists('hotel_contact_fields', $data)) {
                $product->hotelContactFields()->delete();
                foreach ($this->decodeJsonPayload($data['hotel_contact_fields'] ?? [], []) as $hf) {
                    $product->hotelContactFields()->create([
                        'field' => $hf['field'] ?? '',
                        'required' => $hf['required'] ?? false,
                        'requiredBeforeDeparture' => $hf['requiredBeforeDeparture'] ?? false,
                    ]);
                }
            }

            // Booking questions
            if (array_key_exists('booking_questions', $data)) {
                $product->bookingQuestions()->delete();
                foreach ($data['booking_questions'] as $bq) {
                    $product->bookingQuestions()->create([
                        'personalData' => $bq['personalData'] ?? false,
                        'required'     => $bq['required'] ?? false,
                        'selectFromOptions' => $bq['selectFromOptions'] ?? false,
                        'selectMultiple'    => $bq['selectMultiple'] ?? false,
                        'requiredBeforeDeparture' => $bq['requiredBeforeDeparture'] ?? false,
                        'questionCode' => $bq['questionCode'] ?? null,
                        'label'        => $bq['label'] ?? null,
                        'help'         => $bq['help'] ?? null,
                        'placeholder'  => $bq['placeholder'] ?? null,
                        'dataType'     => $bq['dataType'] ?? null,
                        'context'      => $bq['context'] ?? null,
                        'pricingCategoryTriggerSelection' => $bq['pricingCategoryTriggerSelection'] ?? null,
                        'rateTriggerSelection' => $bq['rateTriggerSelection'] ?? null,
                        'extraTriggerSelection' => $bq['extraTriggerSelection'] ?? null,
                        'options'      => is_array($bq['options'] ?? null) ? $bq['options'] : (is_string($bq['options'] ?? null) ? json_decode($bq['options'], true) : []),
                        'flags'        => is_array($bq['flags'] ?? null) ? $bq['flags'] : (is_string($bq['flags'] ?? null) ? json_decode($bq['flags'], true) : []),
                    ]);
                }
            }

            // Price
            if (!empty($data['price'])) {
                $product->price()->updateOrCreate(
                    ['product_id' => $product->id, 'source_type' => 'manual', 'price_kind' => 'summary'],
                    [
                        'provider'       => strtolower((string) ($data['provider'] ?? $data['platform'] ?? $product->provider ?? 'manual')),
                        'valid_from'     => $data['price']['from'] ?? null,
                        'valid_to'       => $data['price']['to'] ?? null,
                        'selling_price'  => (float) ($data['price']['amount'] ?? 0),
                        'original_price' => (float) ($data['price']['amount'] ?? 0),
                        'currency'       => strtoupper((string) ($data['price']['defaultCurrency'] ?? 'EUR')),
                        'is_default'     => true,
                        'sort_order'     => 0,
                        'metadata'       => [
                            'is_price_converted' => (bool) ($data['price']['isPriceConverted'] ?? false),
                            'rates'              => $this->decodeJsonPayload($data['price']['rates'] ?? null, []),
                        ],
                    ]
                );
            }

            // Cancellation policies
            if (array_key_exists('cancellation_policies', $data)) {
                $product->cancellationPolicies()->delete();
                $provider = strtolower((string) ($data['provider'] ?? $data['platform'] ?? $product->provider));
                foreach ($data['cancellation_policies'] as $cp) {
                    $product->cancellationPolicies()->create([
                        'provider'   => $provider,
                        'policy'     => $cp['policy'],
                        'policyType' => $cp['policyType'] ?? null,
                        'tax'        => $cp['tax'] ?? null,
                        'details'    => is_array($cp['details'] ?? null) ? $cp['details'] : [],
                    ]);
                }
            }
        });

        // Images deletion (outside transaction — operates on legacy Image model, not ProductImage)
        if (!empty($data['deleted_image_ids'])) {
            LegacyImage::whereIn('id', $data['deleted_image_ids'])
                ->where('product_id', $product->id)
                ->each(function ($image) {
                foreach (['original', 'large', 'preview'] as $variant) {
                    if ($image->$variant) {
                        $path = str_replace(url('storage/'), '', $image->$variant);
                        if (Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                }
                $image->delete();
            });
        }

        // New images (LegacyImage model)
        if (!empty($files['new_images'])) {
            foreach ($files['new_images'] as $row) {
                $dataToSave = ['product_id' => $product->id];
                foreach (['original', 'large', 'preview'] as $variant) {
                    if (!empty($row[$variant]) && $row[$variant] instanceof UploadedFile) {
                        $dataToSave[$variant] = url('storage/' . $this->imageUploadService->upload($row[$variant], "products/{$product->id}/images"));
                    }
                }
                LegacyImage::create($dataToSave);
            }
        }

        return $product;
    }

    public function delete(Product $product): void
    {
        $thumbnail = $product->getRawOriginal('thumbnail');
        $this->imageUploadService->delete($thumbnail);
        $product->delete();
    }

    public function bulkDelete(array $ids): array
    {
        $products = Product::whereIn('id', $ids)->get();
        $count = 0;

        foreach ($products as $product) {
            $thumbnail = $product->getRawOriginal('thumbnail');
            $this->imageUploadService->delete($thumbnail);
            $product->delete();
            $count++;
        }

        return ['success' => true, 'count' => $count];
    }

    // ─────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────

    private function resolveVendorId(array $data, string $provider): ?int
    {
        if ((bool) ($data['use_existing_vendor'] ?? false)) {
            return (int) ($data['vendor_id'] ?? 0) ?: null;
        }

        $vendorData = $data['vendor'] ?? [];
        $vendorData['provider'] = $provider;
        return Vendor::create($vendorData)->id;
    }

    private function resolveCategoryId(array $data): ?int
    {
        if ((bool) ($data['use_existing_category'] ?? false)) {
            return (int) ($data['category_id'] ?? 0) ?: null;
        }

        $categoryData = $data['category'] ?? [];
        if (!empty($categoryData['slug']) || !empty($categoryData['name'])) {
            $slug = !empty($categoryData['slug']) ? Str::slug($categoryData['slug']) : Str::slug($categoryData['name']);
            $category = Categories::where('slug', $slug)->first();
            return $category ? $category->id : null;
        }

        return null;
    }

    private function generateUniqueSlug(string $value): string
    {
        $baseSlug = Str::slug($value) ?: Str::uuid()->toString();
        $slug = $baseSlug;
        $counter = 2;
        while ($this->productRepository->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter++;
        }
        return $slug;
    }

    private function decodeJsonPayload(mixed $value, array $default = []): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return $default;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    private function normalizeProductContentLists(array $data): array
    {
        return [
            'activityAttributes' => $this->normalizeStringList($data['activityAttributes'] ?? []),
            'highlights'         => $this->normalizeStringList($data['highlights'] ?? []),
            'inclusions'         => $this->normalizeStringList($data['inclusions'] ?? []),
            'exclusions'         => $this->normalizeStringList($data['exclusions'] ?? []),
        ];
    }

    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) return [];
        return collect($value)
            ->filter(fn($item) => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn($item) => trim((string) $item))
            ->values()
            ->all();
    }

    private function normalizeItinerary(mixed $value): array
    {
        if (is_string($value)) $value = $this->decodeJsonPayload($value, []);
        if (!is_array($value)) return [];
        return collect($value)
            ->filter(fn($item) => is_array($item))
            ->map(function (array $item, int $index) {
                $title = trim((string) ($item['title'] ?? ''));
                $description = trim((string) ($item['description'] ?? ''));
                $location = trim((string) ($item['location'] ?? ''));
                if ($title === '' && $description === '' && $location === '') return null;
                return [
                    'title'       => $title !== '' ? $title : 'Day ' . ($index + 1),
                    'description' => $description,
                    'location'    => $location,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function normalizeBookingQuestions(array $questions): array
    {
        return collect($questions)
            ->filter(fn(array $q) => filled($q['label'] ?? null) || filled($q['questionCode'] ?? null))
            ->map(function (array $q, int $index) {
                return [
                    'personalData'  => (bool) ($q['personalData'] ?? false),
                    'required'      => (bool) ($q['required'] ?? false),
                    'selectFromOptions' => (bool) ($q['selectFromOptions'] ?? false),
                    'selectMultiple'    => (bool) ($q['selectMultiple'] ?? false),
                    'requiredBeforeDeparture' => (bool) ($q['requiredBeforeDeparture'] ?? false),
                    'questionCode'  => $q['questionCode'] ?? null,
                    'label'         => $q['label'] ?? null,
                    'help'          => $q['help'] ?? null,
                    'placeholder'   => $q['placeholder'] ?? null,
                    'dataType'      => $q['dataType'] ?? null,
                    'context'       => $q['context'] ?? null,
                    'pricingCategoryTriggerSelection' => $q['pricingCategoryTriggerSelection'] ?? null,
                    'rateTriggerSelection'  => $q['rateTriggerSelection'] ?? null,
                    'extraTriggerSelection' => $q['extraTriggerSelection'] ?? null,
                    'options'       => $this->decodeJsonPayload($q['options'] ?? null, []),
                    'flags'         => $this->decodeJsonPayload($q['flags'] ?? null, []),
                    'sort_order'    => $index,
                ];
            })
            ->values()
            ->all();
    }

    private function syncManualPricing(Product $product, array $priceInput, array $priceConfig): array
    {
        $currency = strtoupper((string) ($priceInput['defaultCurrency'] ?? 'EUR'));
        $fallbackAmount = $this->normalizeMoney($priceInput['amount'] ?? 0) ?? 0.0;
        $fromDate = $priceInput['from'] ?? null;
        $toDate = $priceInput['to'] ?? null;

        $option = ProductOption::create([
            'product_id' => $product->id,
            'provider'   => $product->provider,
            'name'       => 'Default',
            'description'=> 'Manual default option',
            'currency'   => $currency,
            'is_default' => true,
            'sort_order' => 0,
            'metadata'   => ['source' => 'admin_product_create', 'pricing_configuration' => $priceConfig],
        ]);

        $passengers = collect($priceConfig['passengers'] ?? [])
            ->filter(fn($row) => is_array($row))
            ->values();

        if ($passengers->isEmpty()) {
            $passengers = collect([[
                'type' => 'ADULT',
                'price' => ['amount' => $fallbackAmount, 'currency' => $currency],
                'ageRange' => ['min' => 18, 'max' => 99],
                'tieredPrices' => [],
            ]]);
        }

        $pricingCategoriesByCode = [];
        $amounts = [];

        foreach ($passengers as $index => $passenger) {
            $code = strtoupper((string) ($passenger['type'] ?? 'ADULT'));
            $amount = $this->normalizeMoney($passenger['price']['amount'] ?? $fallbackAmount) ?? $fallbackAmount;
            $rowCurrency = strtoupper((string) ($passenger['price']['currency'] ?? $currency));
            $ageRange = is_array($passenger['ageRange'] ?? null) ? $passenger['ageRange'] : [];

            $pricingCat = OptionPricingCategory::create([
                'product_option_id' => $option->id,
                'provider'     => $product->provider,
                'category_code' => $code,
                'category_label' => Str::title(str_replace('_', ' ', strtolower($code))),
                'age_from'     => $ageRange['min'] ?? null,
                'age_to'       => $ageRange['max'] ?? null,
                'occupancy'    => 1,
                'group_size'   => 0,
                'selling_price'    => $amount,
                'original_price'   => $amount,
                'recommended_price' => $amount,
                'currency'     => $rowCurrency,
                'is_default'   => $index === 0,
                'is_age_qualified' => true,
                'sort_order'   => $index,
                'metadata'     => $passenger,
            ]);

            $pricingCategoriesByCode[$code] = $pricingCat;
            $amounts[] = $amount;

            Price::create([
                'product_id'          => $product->id,
                'product_option_id'   => $option->id,
                'pricing_category_id' => $pricingCat->id,
                'provider'            => $product->provider,
                'source_type'         => 'manual',
                'price_kind'          => 'base',
                'category_code'       => $code,
                'category_name'       => $pricingCat->category_label,
                'valid_from'          => $fromDate,
                'valid_to'            => $toDate,
                'selling_price'       => $amount,
                'original_price'      => $amount,
                'recommended_price'   => $amount,
                'currency'            => $rowCurrency,
                'is_default'          => $index === 0,
                'sort_order'          => $index,
                'metadata'            => [
                    'is_price_converted' => (bool) ($priceInput['isPriceConverted'] ?? false),
                    'source' => 'admin_product_create',
                ],
            ]);

            foreach (($passenger['tieredPrices'] ?? []) as $tierIndex => $tier) {
                if (!is_array($tier)) continue;
                $tierAmount = $this->normalizeMoney($tier['amount'] ?? null);
                if ($tierAmount === null) continue;
                TieredPrice::create([
                    'pricing_category_id' => $pricingCat->id,
                    'min_pax'   => max(1, (int) ($tier['minPassengersRequired'] ?? 1)),
                    'max_pax'   => (int) ($tier['maxPassengersAllowed'] ?? 999),
                    'amount'    => $tierAmount,
                    'currency'  => strtoupper((string) ($tier['currency'] ?? $rowCurrency)),
                    'sort_order' => $tierIndex,
                ]);
            }
        }

        // Date-range pricing
        foreach (($priceConfig['pricesByDateRange'] ?? []) as $seasonIndex => $season) {
            if (!is_array($season)) continue;
            foreach (($season['rates']['passengers'] ?? []) as $paxIndex => $passenger) {
                if (!is_array($passenger)) continue;
                $code = strtoupper((string) ($passenger['type'] ?? 'ADULT'));
                $pricingCat = $pricingCategoriesByCode[$code] ?? null;
                $amount = $this->normalizeMoney($passenger['price']['amount'] ?? null);
                if (!$pricingCat || $amount === null) continue;
                Price::create([
                    'product_id'          => $product->id,
                    'product_option_id'   => $option->id,
                    'pricing_category_id' => $pricingCat->id,
                    'provider'     => $product->provider,
                    'source_type'  => 'manual',
                    'price_kind'   => 'date_range',
                    'category_code' => $code,
                    'category_name' => $pricingCat->category_label,
                    'valid_from'   => $season['startDate'] ?? null,
                    'valid_to'     => $season['endDate'] ?? null,
                    'selling_price'    => $amount,
                    'original_price'   => $amount,
                    'recommended_price'=> $amount,
                    'currency'     => strtoupper((string) ($passenger['price']['currency'] ?? $currency)),
                    'is_default'   => false,
                    'sort_order'   => ($seasonIndex + 1) * 100 + $paxIndex,
                    'metadata'     => ['source' => 'admin_product_create', 'season' => $season],
                ]);
            }
        }

        $fromPrice = min($amounts ?: [$fallbackAmount]);
        return ['from_price' => $fromPrice, 'original_price' => $fromPrice, 'currency' => $currency];
    }

    private function syncCancellationPolicies(Product $product, array $policies, string $provider): void
    {
        foreach ($policies as $index => $data) {
            if (trim((string) ($data['policy'] ?? '')) === '') continue;
            CancellationPolicy::create([
                'product_id'    => $product->id,
                'provider'      => $provider,
                'title'         => 'Manual cancellation policy',
                'policy'        => $data['policy'],
                'policyType'    => $data['policyType'] ?? null,
                'tax'           => $data['tax'] ?? null,
                'is_cancellable'=> ($data['policyType'] ?? null) !== 'NON_REFUNDABLE',
                'penalty_rules' => is_array($data['details'] ?? null) ? $data['details'] : [],
                'sort_order'    => $index,
            ]);
        }
    }

    private function syncKeywords(Product $product, array $keywords, string $provider): void
    {
        foreach ($keywords as $index => $keywordName) {
            $name = trim((string) $keywordName);
            if ($name === '') continue;
            Keyword::create([
                'product_id' => $product->id,
                'provider'   => $provider,
                'name'       => $name,
                'slug'       => Str::slug($name),
                'type'       => 'tag',
                'sort_order' => $index,
                'meta'       => null,
            ]);
        }
    }

    private function storeProductImages(Product $product, array $imageFiles, string $provider): ?string
    {
        $primaryImagePath = null;

        foreach ($imageFiles as $index => $row) {
            $originalPath = !empty($row['original']) && $row['original'] instanceof UploadedFile
                ? $this->imageUploadService->upload($row['original'], "products/{$product->id}/images")
                : null;
            $largePath = !empty($row['large']) && $row['large'] instanceof UploadedFile
                ? $this->imageUploadService->upload($row['large'], "products/{$product->id}/images")
                : null;
            $previewPath = !empty($row['preview']) && $row['preview'] instanceof UploadedFile
                ? $this->imageUploadService->upload($row['preview'], "products/{$product->id}/images")
                : null;

            $bestPath = $previewPath ?? $largePath ?? $originalPath;

            ProductImage::create([
                'product_id'    => $product->id,
                'provider'      => $provider,
                'original_url'  => $originalPath,
                'large_url'     => $largePath,
                'preview_url'   => $previewPath,
                'alt_text'      => $product->title,
                'is_primary'    => $index === 0,
                'is_active'     => true,
                'sort_order'    => $index,
                'metadata'      => ['source' => 'admin_product_create'],
            ]);

            $primaryImagePath ??= $bestPath;
        }

        return $primaryImagePath;
    }

    private function storeProductVideos(Product $product, array $videoFiles, string $provider): void
    {
        foreach ($videoFiles as $index => $file) {
            if (!$file || !$file->isValid()) continue;
            $relativePath = $this->imageUploadService->upload($file, "products/{$product->id}/videos");
            ProductVideo::create([
                'product_id'    => $product->id,
                'provider'      => $provider,
                'source_url'    => $relativePath,
                'thumbnail_url' => $relativePath,
                'preview_url'   => $relativePath,
                'title'         => $file->getClientOriginalName(),
                'provider_name' => 'Local Upload',
                'mime_type'     => $file->getClientMimeType(),
                'file_size'     => $file->getSize(),
                'is_primary'    => $index === 0,
                'is_active'     => true,
                'sort_order'    => $index,
                'metadata'      => ['source' => 'admin_product_create', 'original_filename' => $file->getClientOriginalName()],
            ]);
        }
    }
}
