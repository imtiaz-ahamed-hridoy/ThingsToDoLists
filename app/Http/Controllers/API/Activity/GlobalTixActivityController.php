<?php

namespace App\Http\Controllers\API\Activity;

use App\Services\GlobalTix\GlobaltixService;
use Illuminate\Http\Request;
use App\Services\GlobalTix\GlobalTixProductService;
use App\Http\Controllers\Controller;
use App\Traits\AllTraits;
use Illuminate\Support\Facades\Log;

class GlobalTixActivityController extends Controller
{
    use AllTraits;

    private GlobalTixProductService $globalTix;

    public function __construct(GlobalTixProductService $globalTix)
    {
        $this->globalTix = $globalTix;
    }

    /**
     * Get a list of activities from GlobalTix.
     */
    public function getActivities(Request $request)
    {
        $request->validate([
            'countryCode' => 'required|string|size:2',
            'city' => 'nullable|string',
            'textSearch' => 'nullable|string',
            'page' => 'integer|min:1',
            'pageSize' => 'integer|min:1|max:100',
        ]);

        try {
            $params = [
                'countryCode' => $request->countryCode,
                'city' => $request->city,
                'textSearch' => $request->textSearch,
                'language' => strtolower($this->resolveLanguage()),
                'page' => $request->get('page', 1),
                'pageSize' => $request->get('pageSize', 10),
                'currency' => $this->resolveCurrency(),
            ];

            $response = $this->globalTix->searchActivities($params);

            return response()->json(['success' => true, 'data' => $response]);
        } catch (\Exception $e) {
            Log::error('GlobalTix getActivities error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch activities',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details for a specific GlobalTix activity.
     */
    public function getActivity(Request $request, $activityId)
    {
        $request->validate([
            'currency' => 'nullable|string',
        ]);

        try {
            // Instantiate the service so we have access to getFullProductData
            $globaltixProvider = app(GlobaltixService::class);
            $response = $globaltixProvider->getFullProductData(
                $activityId,
                strtolower($this->resolveLanguage()),
                $this->resolveCurrency()
            );

            if (!($response['status'] ?? false)) {
                throw new \Exception($response['message'] ?? 'Failed to fetch provider data');
            }

            return response()->json(['success' => true, 'data' => $response['data']]);
        } catch (\Exception $e) {
            Log::error('GlobalTix getActivity error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch activity details',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Check availability for a specific GlobalTix ticket type.
     */
    public function checkAvailability(Request $request)
    {
        // Strict alignment with GlobalTix API document for checkEventAvailability
        $request->validate([
            'dateFrom' => 'required|date|date_format:Y-m-d',
            'dateTo' => 'required|date|date_format:Y-m-d|after_or_equal:dateFrom',
            'ticketTypeID' => 'required|integer',
            'optionID' => 'nullable|integer',
            'timeslot' => 'nullable|string|date_format:H:i',
        ]);

        try {
            $ticketTypeID = (string) $request->input('ticketTypeID');
            $dateFrom = $request->input('dateFrom');
            $dateTo = $request->input('dateTo');
            $optionID = $request->filled('optionID') ? (string) $request->input('optionID') : null;
            $timeslot = $request->input('timeslot');

            $response = $this->globalTix->getTicketAvailability(
                $ticketTypeID,
                $dateFrom,
                $dateTo,
                $optionID,
                $timeslot,
                $this->resolveCurrency()
            );

            if (!$response['status']) {
                throw new \Exception($response['message']);
            }

            return response()->json(['success' => true, 'data' => $response['data']]);
        } catch (\Exception $e) {
            Log::error('GlobalTix checkAvailability error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to check availability',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

