<?php

namespace App\Http\Controllers\API\Activity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Bokun\BokunService;
use App\Traits\AllTraits;

class BokunActivityController extends Controller
{
    use AllTraits;

    private array $priceResponse = [];
    private BokunService $bokun;

    public function __construct(BokunService $bokun)
    {
        $this->bokun = $bokun;
    }



    // ==================== ACTIVITIES ====================

    public function getActivities(Request $request)
    {
        $request->validate([
            'currency' => 'nullable|string',
            'page' => 'integer|min:1',
            'pageSize' => 'integer|min:1|max:100',
        ]);

        try {
            $activities = $this->bokun->getAllActivities(
                strtoupper($this->resolveLanguage()),
                $this->resolveCurrency(),
                $request->get('page', 1),
                $request->get('pageSize', 10)
            );

            return response()->json(['success' => true, 'data' => $activities]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch activities',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getActivity(Request $request, $activityId)
    {
        if (!is_numeric($activityId)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid activity ID',
                'message' => 'Activity ID must be numeric'
            ], 400);
        }

        $request->validate([
            'currency' => 'nullable|string',
        ]);

        try {
            // Instantiate the service so we have access to getFullProductData
            $bokunProvider = app(\App\Services\Bokun\BokunService::class);
            $response = $bokunProvider->getFullProductData(
                $activityId,
                strtoupper($this->resolveLanguage()),
                $this->resolveCurrency()
            );

            if (!($response['status'] ?? false)) {
                 throw new \Exception($response['message'] ?? 'Failed to fetch provider data');
            }

            return response()->json(['success' => true, 'data' => $response['data']]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch activity',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'activity_id' => 'required|integer',
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        try {
            $currency = $this->resolveCurrency();

            $availability = $this->bokun->getAvailableDates(
                $request->activity_id,
                $request->start_date,
                $request->end_date,
                $currency,
                strtoupper($this->resolveLanguage())
            );

            $priceList = $this->bokun->getActivityPriceList($request->activity_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'availability' => $availability,
                    'price_list' => $priceList
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to check availability',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getPickupPlaces($activityId)
    {
        if (!is_numeric($activityId)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid activity ID',
                'message' => 'Activity ID must be numeric'
            ], 400);
        }

        try {
            $pickupPlaces = $this->bokun->getPickupPlaces($activityId);
            return response()->json(['success' => true, 'data' => $pickupPlaces]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch pickup places',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

