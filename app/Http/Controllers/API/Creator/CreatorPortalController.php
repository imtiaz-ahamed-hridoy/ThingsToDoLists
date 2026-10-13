<?php

namespace App\Http\Controllers\API\Creator;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\CreatorCommunityApplication;
use App\Models\CreatorSponsoredExperienceApplication;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreatorPortalController extends Controller
{
    use ApiResponse;

    public function landing(Request $request): JsonResponse
    {
        $user = $request->user();
        $communityApplication = $user->creatorCommunityApplications()->latest()->first();
        $sponsoredApplications = $user->creatorSponsoredExperienceApplications()->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Creator portal data loaded successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => $user->avatar,
                ],
                'creator' => [
                    'id' => $user->creator?->id,
                    'creator_type' => $user->creator?->creator_type,
                    'organization_name' => $user->creator?->organization_name,
                    'currency' => $user->creator?->currency,
                    'country' => $user->creator?->country,
                    'posts_on_website' => $user->creator?->posts_on_website,
                    'website_url' => $user->creator?->website_url,
                    'posts_on_social_media' => $user->creator?->posts_on_social_media,
                    'instagram_handle' => $user->creator?->instagram_handle,
                    'youtube_account' => $user->creator?->youtube_account,
                    'tiktok_account' => $user->creator?->tiktok_account,
                    'main_channel' => $user->creator?->main_channel,
                ],
                'application_status' => [
                    'community' => $communityApplication ? $this->communityApplicationResource($communityApplication) : null,
                    'sponsored_experiences' => $sponsoredApplications->map(fn (CreatorSponsoredExperienceApplication $application) => $this->sponsoredApplicationResource($application))->values()->all(),
                ],
                'permissions' => [
                    'can_submit_community_application' => $communityApplication === null || $communityApplication->status !== 'approved',
                    'can_submit_sponsored_application' => $communityApplication !== null,
                ],
            ],
        ]);
    }

    public function communityApplication(Request $request): JsonResponse
    {
        $user = $request->user();
        $latestApplication = $user->creatorCommunityApplications()->latest()->first();

        return response()->json([
            'status' => true,
            'message' => 'Creator community application data loaded successfully.',
            'data' => [
                'existing_application' => $latestApplication ? $this->communityApplicationResource($latestApplication) : null,
                'prefill' => [
                    'email' => $latestApplication?->email ?? $user->email,
                    'name' => $latestApplication?->name ?? $user->name,
                    'instagram_handle' => $latestApplication?->instagram_handle ?? $user->creator?->instagram_handle,
                    'instagram_followers' => $latestApplication?->instagram_followers,
                    'youtube_handle' => $latestApplication?->youtube_handle ?? $user->creator?->youtube_account,
                    'tiktok_handle' => $latestApplication?->tiktok_handle ?? $user->creator?->tiktok_account,
                    'website_url' => $latestApplication?->website_url ?? $user->creator?->website_url,
                    'has_base_location' => $latestApplication?->has_base_location,
                    'country' => $latestApplication?->country ?? $user->creator?->country,
                    'city' => $latestApplication?->city,
                    'discovered_via' => $latestApplication?->discovered_via,
                    'discovered_via_other' => $latestApplication?->discovered_via_other,
                    'referred_by' => $latestApplication?->referred_by,
                ],
                'options' => [
                    'base_location' => [
                        ['value' => true, 'label' => 'I have a base location'],
                        ['value' => false, 'label' => 'I do not have a base location and travel full time'],
                    ],
                    'countries' => $this->countryOptions(),
                    'discovered_via' => [
                        ['value' => 'referred_by_friend', 'label' => 'Referred by a friend'],
                        ['value' => 'ad_on_instagram', 'label' => 'Ad on Instagram'],
                        ['value' => 'affiliate_onboarding_email', 'label' => 'Affiliate onboarding email'],
                        ['value' => 'influencer_promotion', 'label' => 'Influencer promotion'],
                        ['value' => 'other', 'label' => 'Other'],
                    ],
                ],
            ],
        ]);
    }

    public function storeCommunityApplication(Request $request): JsonResponse
    {
        $request->merge($this->prepareCommunityApplicationPayload($request));

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:100'],
            'instagram_handle' => ['required', 'string', 'max:255'],
            'instagram_followers' => ['required', 'integer', 'min:3000', 'max:100000000'],
            'youtube_handle' => ['nullable', 'string', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'tiktok_handle' => ['nullable', 'string', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'has_base_location' => ['required', 'boolean'],
            'country' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'discovered_via' => ['required', 'string', 'in:referred_by_friend,referral,ad_on_instagram,affiliate_onboarding_email,influencer_promotion,other'],
            'discovered_via_other' => ['nullable', 'string', 'max:255'],
            'referred_by' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ], ['instagram_followers.min' => 'You need at least 3000 followers to join the community.']);

        if ($validated['discovered_via'] === 'other' && empty($validated['discovered_via_other'])) {
            throw ValidationException::withMessages(['discovered_via_other' => ['Please tell us where you heard about the creator community.']]);
        }

        $user = $request->user();
        $youtubeHandle = $validated['youtube_handle'] ?? $validated['youtube_url'] ?? null;
        $tiktokHandle = $validated['tiktok_handle'] ?? $validated['tiktok_url'] ?? null;

        $application = CreatorCommunityApplication::updateOrCreate(
            ['user_id' => $user->id, 'status' => 'submitted'],
            [
                'user_id' => $user->id,
                'email' => $validated['email'],
                'name' => $validated['name'],
                'instagram_handle' => $validated['instagram_handle'],
                'instagram_followers' => $validated['instagram_followers'],
                'youtube_handle' => $youtubeHandle,
                'tiktok_handle' => $tiktokHandle,
                'website_url' => $validated['website_url'] ?? null,
                'has_base_location' => $request->boolean('has_base_location'),
                'country' => $validated['country'],
                'city' => $validated['city'],
                'discovered_via' => $validated['discovered_via'],
                'discovered_via_other' => $validated['discovered_via_other'] ?? null,
                'referred_by' => $validated['referred_by'] ?? null,
                'status' => 'submitted',
                'raw_payload' => [...$validated, 'youtube_handle' => $youtubeHandle, 'tiktok_handle' => $tiktokHandle, 'has_base_location' => $request->boolean('has_base_location')],
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Community application submitted successfully.',
            'data' => [...$this->communityApplicationResource($application), 'follow_up_eta_business_days' => 7],
        ], 201);
    }

    public function sponsoredExperience(Request $request): JsonResponse
    {
        $user = $request->user();
        $latestApplication = $user->creatorSponsoredExperienceApplications()->latest()->first();
        $communityApplication = $user->creatorCommunityApplications()->latest()->first();

        return response()->json([
            'status' => true,
            'message' => 'Creator sponsored experience data loaded successfully.',
            'data' => [
                'eligible' => [
                    'can_apply' => $communityApplication !== null,
                    'reason' => $communityApplication !== null ? null : 'You need an existing creator community application before applying for a sponsored experience.',
                ],
                'community_application' => $communityApplication ? $this->communityApplicationResource($communityApplication) : null,
                'existing_application' => $latestApplication ? $this->sponsoredApplicationResource($latestApplication) : null,
                'prefill' => [
                    'is_community_member' => $communityApplication !== null,
                    'name' => $latestApplication?->name ?? $user->name,
                    'email' => $latestApplication?->email ?? $user->email,
                    'instagram_url' => $latestApplication?->instagram_url,
                    'preferred_currency' => $latestApplication?->preferred_currency ?? $user->creator?->currency,
                    'reel_views_range' => $latestApplication?->reel_views_range,
                    'planned_experience_date' => optional($latestApplication?->planned_experience_date)->format('Y-m-d'),
                    'feature_consent' => $latestApplication?->feature_consent,
                ],
                'options' => [
                    'currencies' => $this->currencyOptions(),
                    'reel_views_ranges' => [
                        ['value' => 'under_10k', 'label' => 'Less than 10k total'],
                        ['value' => '10k_to_25k', 'label' => 'Between 10k and 25k total'],
                        ['value' => '25k_to_50k', 'label' => 'Between 25k and 50k total'],
                        ['value' => '50k_to_100k', 'label' => 'Between 50k and 100k total'],
                        ['value' => '100k_to_150k', 'label' => 'Between 100k and 150k total'],
                        ['value' => '150k_to_200k', 'label' => 'Between 150k and 200k total'],
                    ],
                ],
            ],
        ]);
    }

    public function storeSponsoredExperience(Request $request): JsonResponse
    {
        $request->merge($this->prepareSponsoredExperiencePayload($request));

        $allowedCurrencies = array_column($this->currencyOptions(), 'value');

        $validated = $request->validate([
            'is_community_member' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'instagram_url' => ['required', 'url', 'max:255'],
            'preferred_currency' => ['required', 'string', Rule::in($allowedCurrencies)],
            'reel_views_range' => ['required', 'string', 'in:under_10k,10k_to_25k,25k_to_50k,50k_to_100k,100k_to_150k,150k_to_200k'],
            'planned_experience_date' => ['required', 'date', 'after:today'],
            'no_go_experiences_accepted' => ['required', 'accepted'],
            'feature_consent' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $communityApplication = $user->creatorCommunityApplications()->latest()->first();

        if (! $request->boolean('is_community_member') || ! $communityApplication) {
            throw ValidationException::withMessages([
                'is_community_member' => ['You need to be part of the Creator Community to apply for a sponsored experience.'],
            ]);
        }

        $application = CreatorSponsoredExperienceApplication::create([
            'user_id' => $user->id,
            'creator_community_application_id' => $communityApplication->id,
            'is_community_member' => true,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'instagram_url' => $validated['instagram_url'],
            'preferred_currency' => $validated['preferred_currency'],
            'reel_views_range' => $validated['reel_views_range'],
            'planned_experience_date' => $validated['planned_experience_date'],
            'no_go_experiences_accepted' => true,
            'feature_consent' => $request->boolean('feature_consent'),
            'status' => 'submitted',
            'raw_payload' => [...$validated, 'feature_consent' => $request->boolean('feature_consent')],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Sponsored experience application submitted successfully.',
            'data' => [...$this->sponsoredApplicationResource($application), 'follow_up_eta_business_days' => 7],
        ], 201);
    }

    private function prepareCommunityApplicationPayload(Request $request): array
    {
        return [
            'email' => $request->input('email'),
            'name' => $request->input('name', trim($request->input('firstName') . ' ' . $request->input('lastName'))),
            'instagram_handle' => $request->input('instagram_handle', $request->input('instagramHandle')),
            'instagram_followers' => $request->input('instagram_followers', $request->input('instagramFollower')),
            'youtube_handle' => $request->input('youtube_handle', $request->input('youtubeHandle')),
            'youtube_url' => $request->input('youtube_url'),
            'tiktok_handle' => $request->input('tiktok_handle', $request->input('tiktokHandle')),
            'tiktok_url' => $request->input('tiktok_url'),
            'website_url' => $this->normalizeWebsiteUrl($request->input('website_url', $request->input('websiteLink'))),
            'has_base_location' => $this->normalizeBaseLocationValue($request->input('has_base_location'), $request->input('type')),
            'country' => $this->normalizeCountryValue($request->input('country', $request->input('currency'))),
            'city' => $request->input('city'),
            'discovered_via' => $this->normalizeDiscoverySource($request->input('discovered_via', $request->input('source'))),
            'discovered_via_other' => $request->input('discovered_via_other', $request->input('other')),
            'referred_by' => $request->input('referred_by', $request->input('referred')),
            'phone' => $request->input('phone'),
            'bio' => $request->input('bio'),
        ];
    }

    private function prepareSponsoredExperiencePayload(Request $request): array
    {
        return [
            'is_community_member' => $this->normalizeCommunityMemberValue($request->input('is_community_member'), $request->input('community')),
            'name' => $request->input('name', trim($request->input('firstName') . ' ' . $request->input('lastName'))),
            'email' => $request->input('email'),
            'instagram_url' => $this->normalizeInstagramUrl($request->input('instagram_url', $request->input('instagramHandle'))),
            'preferred_currency' => $this->normalizeSponsoredCurrencyValue($request->input('preferred_currency', $request->input('currency'))),
            'reel_views_range' => $this->normalizeReelViewsRange($request->input('reel_views_range', $request->input('views'))),
            'planned_experience_date' => $this->normalizeDateValue($request->input('planned_experience_date', $request->input('date'))),
            'no_go_experiences_accepted' => $this->normalizeAcceptedValue($request->input('no_go_experiences_accepted'), $request->input('experience')),
            'feature_consent' => $this->normalizeFeatureConsentValue($request->input('feature_consent'), $request->input('agree')),
        ];
    }

    private function communityApplicationResource(CreatorCommunityApplication $application): array
    {
        return [
            'id' => $application->id,
            'status' => $application->status,
            'submitted_at' => optional($application->created_at)->toISOString(),
            'email' => $application->email,
            'name' => $application->name,
            'instagram_handle' => $application->instagram_handle,
            'instagram_followers' => $application->instagram_followers,
            'youtube_handle' => $application->youtube_handle,
            'tiktok_handle' => $application->tiktok_handle,
            'website_url' => $application->website_url,
            'has_base_location' => $application->has_base_location,
            'country' => $application->country,
            'city' => $application->city,
            'discovered_via' => $application->discovered_via,
            'discovered_via_other' => $application->discovered_via_other,
            'referred_by' => $application->referred_by,
        ];
    }

    private function sponsoredApplicationResource(CreatorSponsoredExperienceApplication $application): array
    {
        return [
            'id' => $application->id,
            'status' => $application->status,
            'submitted_at' => optional($application->created_at)->toISOString(),
            'is_community_member' => $application->is_community_member,
            'name' => $application->name,
            'email' => $application->email,
            'instagram_url' => $application->instagram_url,
            'preferred_currency' => $application->preferred_currency,
            'reel_views_range' => $application->reel_views_range,
            'planned_experience_date' => optional($application->planned_experience_date)->format('Y-m-d'),
            'feature_consent' => $application->feature_consent,
        ];
    }

    private function normalizeDiscoverySource(?string $value): ?string
    {
        return match ($value) {
            'friend', 'referral' => 'referred_by_friend',
            'instagram' => 'ad_on_instagram',
            'email' => 'affiliate_onboarding_email',
            'influencer-promotion' => 'influencer_promotion',
            default => $value,
        };
    }

    private function normalizeCountryValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (Str::lower(trim($value))) {
            'm@example.com' => 'France',
            'm@google.com' => 'Spain',
            'm@support.com' => 'Saudi Arabia',
            default => trim($value),
        };
    }

    private function normalizeBaseLocationValue(mixed $hasBaseLocation, mixed $type): ?bool
    {
        if ($hasBaseLocation !== null) {
            return filter_var($hasBaseLocation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return match ($type) {
            'location' => true,
            'location-none' => false,
            default => null,
        };
    }

    private function normalizeCommunityMemberValue(mixed $isCommunityMember, mixed $community): ?bool
    {
        if ($isCommunityMember !== null) {
            return filter_var($isCommunityMember, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return match ($community) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    private function normalizeSponsoredCurrencyValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (Str::lower(trim($value))) {
            'euro', 'eur' => 'EUR',
            'usd' => 'USD',
            'gbp' => 'GBP',
            default => Str::upper(trim($value)),
        };
    }

    private function normalizeReelViewsRange(?string $value): ?string
    {
        return match ($value) {
            'less10' => 'under_10k',
            'less25' => '10k_to_25k',
            'less50' => '25k_to_50k',
            'less100' => '50k_to_100k',
            'less150' => '100k_to_150k',
            'less200' => '150k_to_200k',
            default => $value,
        };
    }

    private function normalizeDateValue(mixed $value): mixed
    {
        if (! filled($value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizeInstagramUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $trimmed = trim($value);

        if (Str::startsWith($trimmed, ['http://', 'https://'])) {
            return $trimmed;
        }

        return 'https://instagram.com/' . ltrim($trimmed, '@/');
    }

    private function normalizeAcceptedValue(mixed $value, mixed $fallback): mixed
    {
        if ($value !== null) {
            return $value;
        }

        return $fallback;
    }

    private function normalizeFeatureConsentValue(mixed $featureConsent, mixed $agree): ?bool
    {
        if ($featureConsent !== null) {
            return filter_var($featureConsent, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return match ($agree) {
            'option1' => true,
            'option2' => false,
            default => null,
        };
    }

    private function normalizeWebsiteUrl(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $trimmed = trim($value);

        if (Str::startsWith($trimmed, ['http://', 'https://'])) {
            return $trimmed;
        }

        return 'https://' . ltrim($trimmed, '/');
    }

    private function currencyOptions(): array
    {
        $labels = [
            'USD' => 'US Dollars',
            'EUR' => 'Euro',
            'GBP' => 'Pound Sterling',
            'AUD' => 'Australian Dollar',
            'CHF' => 'Swiss Franc',
            'CAD' => 'Canadian Dollar',
        ];

        $dynamicCodes = City::query()->whereNotNull('currency_code')->distinct()->orderBy('currency_code')->pluck('currency_code')->filter()->values()->all();
        $codes = array_values(array_unique(array_merge(array_keys($labels), $dynamicCodes)));

        return array_map(function (string $code) use ($labels): array {
            return ['value' => strtoupper($code), 'label' => $labels[strtoupper($code)] ?? strtoupper($code)];
        }, $codes);
    }

    private function countryOptions(): array
    {
        $countries = City::query()
            ->select('country_name', 'country_short_code')
            ->whereNotNull('country_name')
            ->distinct()
            ->orderBy('country_name')
            ->get()
            ->map(fn (City $city) => ['value' => $city->country_name, 'label' => $city->country_name, 'code' => $city->country_short_code])
            ->values()
            ->all();

        if (! empty($countries)) {
            return $countries;
        }

        return [
            ['value' => 'France', 'label' => 'France', 'code' => 'FR'],
            ['value' => 'Spain', 'label' => 'Spain', 'code' => 'ES'],
            ['value' => 'Saudi Arabia', 'label' => 'Saudi Arabia', 'code' => 'SA'],
        ];
    }
}

