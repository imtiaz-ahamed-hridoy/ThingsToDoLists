<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DestinationHighlightDetailResource;
use App\Http\Resources\DestinationHighlightResource;
use App\Services\DestinationHighlightService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DestinationHighlightApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private DestinationHighlightService $service
    ) {}

    /**
     * GET /api/v1/destination-highlights/{destinationSlug}
     *
     * Returns all active Destination Highlights for the given city or country slug.
     */
    public function index(Request $request, string $destinationSlug): JsonResponse
    {
        $cacheKey = 'destination:highlights:slug:' . md5($destinationSlug);

        $response = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($destinationSlug) {
            $result = $this->service->getHighlightsBySlug($destinationSlug);

            if (!$result['resolved']) {
                return null;
            }

            $city   = $result['resolved']['city'];
            $isCity = $result['resolved']['isCity'];

            $destination = $isCity ? $city->city_name : $city->country_name;

            return [
                'destination'      => $destination,
                'destination_type' => $isCity ? 'city' : 'country',
                'highlights'       => DestinationHighlightResource::collection($result['highlights'])->resolve(),
            ];
        });

        if (!$response) {
            return $this->error('Destination not found.', 404);
        }

        return $this->success('Destination highlights fetched successfully.', $response);
    }

    /**
     * GET /api/v1/destination-highlights/detail/{id}
     *
     * Returns a single Destination Highlight with all its products.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $cacheKey = 'destination:highlight:' . $id;

        $highlight = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($id) {
            return $this->service->getHighlightById($id);
        });

        if (!$highlight) {
            return $this->error('Destination highlight not found.', 404);
        }

        return $this->success(
            'Destination highlight fetched successfully.',
            new DestinationHighlightDetailResource($highlight)
        );
    }
}
