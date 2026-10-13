<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Storage;
use App\Services\GlobalTix\GlobalTixProductService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->resource->relationLoaded('product') ? $this->product : null;
        $location = $product?->relationLoaded('location') ? $product->location : null;
        $location ??= $product?->relationLoaded('locations') ? $product->locations->first() : null;
        $productImages = $product?->relationLoaded('productImages') ? $product->productImages : collect();
        $productImage = $productImages->first();
        $locationData = $this->locationData($product, $location);

        return [
            'id' => $this->id,
            'user_id' => (int) ($this->user_id ?? 0),
            'product_id' => (int) ($this->product_id ?? 0),
            'ratings' => round((float) ($this->ratings ?? 0), 1),
            'rating_display' => sprintf('%.1f/5', round((float) ($this->ratings ?? 0), 1)),
            'comment' => $this->comment ?? '',
            'review_images' => $this->reviewImages(),
            'status' => $this->status ?: 'Active',
            'created_at' => $this->created_at?->format('d-m-Y') ?? '',
            'created_at_human' => $this->created_at?->format('M d, Y') ?? '',
            'updated_at' => $this->updated_at?->format('d-m-Y') ?? '',
            'user' => [
                'id' => (int) ($this->user_id ?? 0),
                'name' => $this->users?->name ?? '',
                'avatar' => $this->users?->avatar ?? null,
            ],
            'product' => [
                'id' => (int) ($product?->id ?? $this->product_id ?? 0),
                'title' => $product?->title ?? '',
                'short_summary' => $this->shortSummary($product),
                'thumbnail' => $this->thumbnail($product, $productImage),
                'total_review_count' => (int) ($product?->total_review_count ?? $product?->reviewCount ?? 0),
                'city' => $locationData['city'],
                'country' => $locationData['country'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function reviewImages(): array
    {
        return collect($this->images ?? [])
            ->filter(fn($image) => is_string($image) && $image !== '')
            ->map(function (string $image): string {
                if (Str::startsWith($image, ['http://', 'https://'])) {
                    return $this->cleanUrl($image);
                }

                return $this->cleanUrl($this->resolveMediaUrl($image) ?? $image);
            })
            ->values()
            ->all();
    }

    private function shortSummary($product): string
    {
        if (! $product) {
            return '';
        }

        $summary = $product->short_introduction
            ?? $product->highlights_summary
            ?? $product->description
            ?? $product->full_description;

        if ($summary === null || $summary === '') {
            return '';
        }

        return Str::limit(trim(strip_tags((string) $summary)), 160);
    }

    private function thumbnail($product, $productImage): string
    {
        if (! $product) {
            return $this->cleanUrl(asset('frontend/no-image.jpg'));
        }

        $galleryImage = $product->images->first();
        $rawThumbnail = $this->firstRawValue($product, [
            'raw_data.data.activity.keyPhoto.originalUrl',
            'raw_data.data.activity.photos.0.originalUrl',
            'raw_data.data.activity.photos.0.derived.0.url',
            'raw_data.data.activity.imageUrl',
            'raw_data.data.activity.image',
            'raw_data.data.activity.data.imageUrl',
            'raw_data.data.activity.data.image',
            'raw_data.data.activity.data.media.0.url',
            'raw_data.data.activity.data.media.0.path',
            'raw_data.data.activity.data.media.0.name',
            'raw_data.details.data.imageUrl',
            'raw_data.details.data.image',
            'raw_data.formdata.thumbnail',
            'raw_data.formdata.image',
            'raw_data.formdata.images.0.url',
            'raw_data.formdata.images.0.preview',
            'raw_data.formdata.images.0.original',
            'raw_data.formdata.images.0.path',
        ]);

        $thumbnail = $product->thumbnail
            ?? $productImage?->preview_url
            ?? $productImage?->large_url
            ?? $productImage?->original_url
            ?? $galleryImage?->preview
            ?? $galleryImage?->large
            ?? $galleryImage?->original
            ?? $rawThumbnail;

        if ($thumbnail) {
            return $this->normalizeMediaUrl((string) $thumbnail, (string) ($product->provider ?? $product->platform ?? ''));
        }

        return $this->cleanUrl(asset('frontend/no-image.jpg'));
    }

    /**
     * @return array{city: string, country: string}
     */
    private function locationData($product, $location): array
    {
        $city = $this->filledString($location?->city);
        $country = $this->filledString($location?->country);

        if (($city === '' || $country === '') && $product) {
            $directLocation = $this->directProductLocationData($product);
            $city = $city !== '' ? $city : $directLocation['city'];
            $country = $country !== '' ? $country : $directLocation['country'];
        }

        if ($city === '' || $country === '') {
            $rawLocation = $this->rawLocationData($product);
            $city = $city !== '' ? $city : $rawLocation['city'];
            $country = $country !== '' ? $country : $rawLocation['country'];
        }

        if (($city === '' || $country === '') && $product?->relationLoaded('category')) {
            $categoryCity = $product->category?->relationLoaded('city') ? $product->category->city : null;
            $categoryCountry = $this->filledString($product->category?->location_country)
                ?: $this->filledString($categoryCity?->country_name);
            $city = $city !== '' ? $city : $this->filledString($categoryCity?->city_name);
            $country = $country !== '' ? $country : $categoryCountry;
        }

        if (($city === '' || $country === '') && $product) {
            $inferred = $this->inferLocationFromProductText($product);
            $city = $city !== '' ? $city : $inferred['city'];
            $country = $country !== '' ? $country : $inferred['country'];
        }

        return [
            'city' => $city,
            'country' => $country,
        ];
    }

    /**
     * @return array{city: string, country: string}
     */
    private function directProductLocationData($product): array
    {
        $location = $this->filledString($product->customer_visits)
            ?: $this->filledString($product->places)
            ?: $this->filledString($product->address_list);

        return $this->parseLocationString($location);
    }

    /**
     * @return array{city: string, country: string}
     */
    private function rawLocationData($product): array
    {
        if (! $product) {
            return ['city' => '', 'country' => ''];
        }

        $city = $this->firstRawValue($product, [
            'raw_data.data.activity.googlePlace.city',
            'raw_data.data.activity.location.city',
            'raw_data.data.activity.locationCode.location',
            'raw_data.data.activity.locationCode.name',
            'raw_data.data.activity.city',
            'raw_data.data.activity.data.city',
            'raw_data.data.activity.data.location',
            'raw_data.details.data.city',
            'raw_data.formdata.city',
            'raw_data.formdata.location.city',
            'raw_data.formdata.locations.0.city',
            'raw_data.formdata.customer_visits.0.city',
        ]);

        $country = $this->firstRawValue($product, [
            'raw_data.data.activity.googlePlace.country',
            'raw_data.data.activity.location.country',
            'raw_data.data.activity.locationCode.countryName',
            'raw_data.data.activity.country',
            'raw_data.data.activity.data.country',
            'raw_data.details.data.country',
            'raw_data.formdata.country',
            'raw_data.formdata.location.country',
            'raw_data.formdata.locations.0.country',
            'raw_data.formdata.customer_visits.0.country',
        ]);

        if ($city === '' || $country === '') {
            $visit = $this->firstRawValue($product, [
                'raw_data.formdata.customer_visits.0',
                'raw_data.formdata.locations.0',
            ]);
            $parsed = $this->parseLocationString($visit);
            $city = $city !== '' ? $city : $parsed['city'];
            $country = $country !== '' ? $country : $parsed['country'];
        }

        return [
            'city' => $city,
            'country' => $country,
        ];
    }

    /**
     * @return array{city: string, country: string}
     */
    private function inferLocationFromProductText($product): array
    {
        $text = trim(implode(' ', array_filter([
            $product->title,
            $product->slug,
            $product->short_introduction,
            $product->description,
            $product->full_description,
        ])));

        if ($text === '') {
            return ['city' => '', 'country' => ''];
        }

        $words = preg_split('/[^a-zA-Z]+/', $text) ?: [];
        $words = array_values(array_filter(array_map(
            fn($word) => Str::lower(trim($word)),
            $words
        )));

        $phrases = [];
        $count = count($words);
        for ($length = 1; $length <= 3; $length++) {
            for ($index = 0; $index <= $count - $length; $index++) {
                $phrases[] = implode(' ', array_slice($words, $index, $length));
            }
        }

        $phrases = array_values(array_unique(array_filter($phrases)));
        if ($phrases === []) {
            return ['city' => '', 'country' => ''];
        }

        $city = DB::table('cities')
            ->whereIn(DB::raw('LOWER(city_name)'), $phrases)
            ->orderByRaw('LENGTH(city_name) DESC')
            ->first(['city_name', 'country_name']);

        return [
            'city' => $this->filledString($city?->city_name),
            'country' => $this->filledString($city?->country_name),
        ];
    }

    private function firstRawValue($product, array $paths): string
    {
        $raw = $product?->raw_response;

        foreach ($paths as $path) {
            $value = data_get($raw, $path);
            $value = $this->filledString($value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{city: string, country: string}
     */
    private function parseLocationString(string $value): array
    {
        if ($value === '') {
            return ['city' => '', 'country' => ''];
        }

        $parts = array_map('trim', explode(',', $value, 2));

        return [
            'city' => $parts[0] ?? '',
            'country' => $parts[1] ?? '',
        ];
    }

    private function filledString(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : '';
    }

    private function normalizeMediaUrl(string $value, string $provider): string
    {
        if ($value === '') {
            return $this->cleanUrl(asset('frontend/no-image.jpg'));
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $this->cleanUrl($value);
        }

        if (strtolower($provider) === 'globaltix') {
            return $this->cleanUrl(app(GlobalTixProductService::class)->getImage($value) ?? asset('frontend/no-image.jpg'));
        }

        if (Str::startsWith($value, ['frontend/', 'backend/'])) {
            return $this->cleanUrl(asset($value));
        }

        return $this->cleanUrl($this->resolveMediaUrl($value) ?? $value);
    }

    private function cleanUrl(string $url): string
    {
        return preg_replace('/([^:])\/{2,}/', '$1/', $url) ?? $url;
    }

    private function resolveMediaUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $cleanPath = ltrim($path, '/');
        if (strpos($cleanPath, 'storage/') === 0) {
            $cleanPath = substr($cleanPath, 8);
        }

        return Storage::disk('public')->url($cleanPath);
    }
}
