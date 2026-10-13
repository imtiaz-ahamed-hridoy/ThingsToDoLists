<?php

namespace App\Http\Controllers\API\Provider;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Provider\ProviderFactory;
use App\Traits\HasCurrency;
use App\Traits\HasLanguage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CheckAvailabilityController extends Controller
{
    use HasLanguage, HasCurrency;

    /**
     * POST /api/v1/check-availability
     *
     * Strict proxy for official availability APIs.
     */
    public function checkAvailability(Request $request)
    {
        // Step 1: Validate Platform
        $request->validate([
            'platform' => 'required|string|in:bokun,globaltix',
        ]);

        $platform = strtolower($request->input('platform'));

        try {
            // Resolve ID based on generic or official payload fields
            $id = $request->input('id') 
                ?? $request->input('product_api_id') 
                ?? $request->input('ticketTypeID');

            // Backward compatibility for DB lookup if only product_id is passed
            if (!$id && $request->has('product_id')) {
                $product = Product::find($request->input('product_id'));
                if ($product) {
                    $id = $product->product_api_id;
                }
            }

            $payload = [];

            // Step 2: Map and strictly validate based on the official API requirements
            if ($platform === 'bokun') {
                $payload['id'] = $id;
                $payload['start'] = $request->input('startdate') ?? $request->input('start_date') ?? $request->input('start');
                $payload['end'] = $request->input('enddate') ?? $request->input('end_date') ?? $request->input('end');
                
                $language = $this->resolveLanguage();
                $payload['lang'] = strtoupper($language);
                
                $currency = $request->input('currency') ?? $this->resolveCurrency();
                if (!empty($currency)) {
                    $payload['currency'] = strtoupper(trim((string) $currency));
                }
                
                if ($request->has('includeSoldOut')) {
                    $payload['includeSoldOut'] = $request->boolean('includeSoldOut');
                }

                $validated = Validator::make($payload, [
                    'id'             => 'required|integer',
                    'start'          => 'required|date_format:Y-m-d',
                    'end'            => 'required|date_format:Y-m-d|after_or_equal:start',
                    'lang'           => 'nullable|string',
                    'currency'       => 'nullable|string',
                    'includeSoldOut' => 'nullable|boolean',
                ])->validate();

            } else {
                // GlobalTix
                $payload['id'] = $id;
                $payload['dateFrom'] = $request->input('startdate') ?? $request->input('start_date') ?? $request->input('dateFrom');
                $payload['dateTo'] = $request->input('enddate') ?? $request->input('end_date') ?? $request->input('dateTo');
                
                
                $language = $this->resolveLanguage();
                $payload['language'] = strtolower($language);

                if ($request->has('timeslot')) {
                    $payload['timeslot'] = $request->input('timeslot');
                }
                if ($request->has('optionID')) {
                    $payload['optionID'] = $request->input('optionID');
                }

                $validated = Validator::make($payload, [
                    'id'       => 'required|integer',
                    'dateFrom' => 'required|date_format:Y-m-d',
                    'dateTo'   => 'required|date_format:Y-m-d|after_or_equal:dateFrom',
                    'language' => 'nullable|string',
                    'timeslot' => 'nullable|string|date_format:H:i',
                    'optionID' => 'nullable|integer',
                ])->validate();
            }

            // Step 3: Instantiate provider using Factory
            $providerService = ProviderFactory::make($platform);

            // Step 4: Call the new proxy method with only the strictly validated payload
            $rawResponse = $providerService->getOfficialAvailability($validated);

            // Step 5: Convert pricing for GlobalTix if needed
            if ($platform === 'globaltix' && isset($rawResponse['data']) && is_array($rawResponse['data'])) {
                $targetCurrency = $this->resolveCurrency();
                
                // Find original currency of this ticket type
                $originalCurrency = 'EUR';
                $price = \App\Models\Price::where('ticket_type_id', $id)->first();
                if ($price) {
                    $originalCurrency = $price->currency ?: ($price->productOption?->currency ?: 'EUR');
                } else {
                    $option = \App\Models\ProductOption::where('external_id', $id)
                        ->orWhere('id', $id)
                        ->first();
                    if ($option) {
                        $originalCurrency = $option->currency ?: 'EUR';
                    }
                }

                if (strtoupper($originalCurrency) !== strtoupper($targetCurrency)) {
                    $currencyService = app(\App\Services\CurrencyService::class);
                    $priceFields = [
                        'nettPrice',
                        'minimumSellingPrice',
                        'recommendedSellingPrice',
                        'originalPrice'
                    ];

                    foreach ($rawResponse['data'] as &$item) {
                        foreach ($priceFields as $field) {
                            if (isset($item[$field]) && is_numeric($item[$field])) {
                                $converted = $currencyService->convert((float) $item[$field], $originalCurrency, $targetCurrency);
                                if ($converted !== null) {
                                    $item[$field] = $converted;
                                }
                            }
                        }
                        $item['currency'] = $targetCurrency;
                    }
                    unset($item);
                } else {
                    foreach ($rawResponse['data'] as &$item) {
                        $item['currency'] = $originalCurrency;
                    }
                    unset($item);
                }
            }

            // Step 6: Return converted response
            return response()->json($rawResponse);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payload validation failed for platform: ' . $platform,
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('CheckAvailability Proxy error', [
                'platform' => $platform,
                'message'  => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
