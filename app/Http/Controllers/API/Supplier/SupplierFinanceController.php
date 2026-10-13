<?php

namespace App\Http\Controllers\API\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\BookingInfoRepositoryInterface;
use App\Repositories\Contracts\VendorInvoiceRepositoryInterface;
use App\Repositories\Contracts\VendorPaymentConfirmationRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class SupplierFinanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private BookingInfoRepositoryInterface $bookingInfoRepository,
        private VendorInvoiceRepositoryInterface $vendorInvoiceRepository,
        private VendorPaymentConfirmationRepositoryInterface $vendorPaymentConfirmationRepository
    ) {}

    /**
     * Supplier finance landing page payload
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $payoutsResponse = json_decode($this->payouts($request)->getContent(), true);
        $invoicesResponse = json_decode($this->invoices($request)->getContent(), true);
        $paymentConfirmationsResponse = json_decode($this->paymentConfirmations($request)->getContent(), true);
        $taxDetailsResponse = json_decode($this->getTaxDetails($request)->getContent(), true);

        return $this->success('Finance dashboard loaded successfully.', [
            'payouts' => $payoutsResponse['data'] ?? [],
            'invoices' => $invoicesResponse['data'] ?? [],
            'payment_confirmations' => $paymentConfirmationsResponse['data'] ?? [],
            'tax_details' => $taxDetailsResponse['data'] ?? [],
        ]);
    }

    /**
     * Get Payouts Overview (Balances and List of Eligible Bookings)
     */
    public function payouts(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $vendorId = $user->vendor->id;

        // Fetch vendor's product APIs IDs to link bookings
        $productApiIds = $this->productRepository->query()->where('vendor_id', $vendorId)
            ->pluck('product_api_id')
            ->filter()
            ->values()
            ->toArray();

        if (empty($productApiIds)) {
            return $this->success('Payouts retrieved successfully.', [
                'balance' => 0.00,
                'next_payout' => 0.00,
                'currency' => 'EUR',
                'payouts' => []
            ]);
        }

        // Fetch bookings related to these products (max 500 latest)
        $bookingsQuery = $this->bookingInfoRepository->query()->whereHas('details', function ($q) use ($productApiIds) {
            $this->whereDetailMatchesAnyProduct($q, $productApiIds);
        });

        // E.g. only completed or confirmed
        $bookings = $bookingsQuery->whereIn('status', ['PAID', 'CONFIRMED', 'COMPLETED'])
            ->with('details')
            ->latest('id')
            ->limit(500)
            ->get();

        $payoutsList = [];
        $balance = 0;

        foreach ($bookings as $booking) {
            // Re-find the specific details that match
            $matchedDetail = $booking->details->first(function ($detail) use ($productApiIds) {
                $activityId = data_get($detail->provider_raw_data, 'activityId') ?? data_get($detail->provider_raw_data, 'productBookings.0.activityId');
                return in_array($activityId, $productApiIds);
            });

            if (!$matchedDetail) continue;

            $productCode = data_get($matchedDetail->provider_raw_data, 'productCode') ?? data_get($matchedDetail->provider_raw_data, 'activityId');
            $optionCode = data_get($matchedDetail->provider_raw_data, 'rateType') ?? 'Standard';
            
            // Assume 80% net rate (20% commission)
            $retailRate = (float) $matchedDetail->price;
            $netRate = $retailRate * 0.8;

            // Generate the payout item payload for UI
            $payoutsList[] = [
                'booking_reference' => $booking->booking_reference,
                'lead_traveler'     => $booking->customer_name ?: 'Unknown',
                'product_code'      => $productCode,
                'option_code'       => $optionCode,
                'activity_date'     => $matchedDetail->date ? $matchedDetail->date->format('d-m-Y') : 'N/A',
                'date_of_purchase'  => $booking->created_at->format('d-m-Y'),
                'retail_rate'       => number_format($retailRate, 2),
                'net_rate'          => number_format($netRate, 2),
                'currency'          => $booking->currency ?: 'EUR',
            ];

            $balance += $netRate;
        }

        return $this->success('Payouts retrieved successfully.', [
            'balance' => number_format($balance, 2, '.', ''),
            'next_payout' => number_format($balance, 2, '.', ''), // Mock same as balance for now
            'currency' => 'EUR',
            'payouts' => $payoutsList
        ]);
    }

    /**
     * List Invoices (filterable by year)
     */
    public function invoices(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $query = $this->vendorInvoiceRepository->query()->where('vendor_id', $user->vendor->id);

        if ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        $invoices = $query->orderBy('date', 'desc')
            ->limit(200)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'date' => $invoice->date->format('d/m/Y'),
                    'invoice' => $invoice->invoice_number,
                    'amount' => '€' . number_format($invoice->amount, 2),
                    'status' => $invoice->status
                ];
            });

        return $this->success('Invoices retrieved successfully.', $invoices);
    }

    /**
     * List Payment Confirmations (filterable by year)
     */
    public function paymentConfirmations(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $query = $this->vendorPaymentConfirmationRepository->query()->where('vendor_id', $user->vendor->id);

        if ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        $confirmations = $query->orderBy('date', 'desc')
            ->limit(200)
            ->get()
            ->map(function ($conf) {
                return [
                    'id' => $conf->id,
                    'date' => $conf->date->format('d/m/Y'),
                    'payment_confirmation' => $conf->confirmation_number,
                    'amount' => '€' . number_format($conf->amount, 2),
                ];
            });

        return $this->success('Payment confirmations retrieved successfully.', $confirmations);
    }

    /**
     * Get Payment and Tax Details
     */
    public function getTaxDetails(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $vendor = $user->vendor;

        return $this->success('Tax details retrieved successfully.', [
            'vat_number' => $vendor->vat_number ?? 'Not specified',
            'tax_identification_number' => $vendor->tax_identification_number ?? 'Not specified',
            'payment_method' => $vendor->payment_method ?? 'Not specified',
        ]);
    }

    /**
     * Update Payment and Tax Details
     */
    public function updateTaxDetails(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['supplier', 'Vendor'], true) || !$user->vendor) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'vat_number' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:255',
        ]);

        $vendor = $user->vendor;
        
        $updateData = [];
        if ($request->has('vat_number')) $updateData['vat_number'] = $request->vat_number;
        if ($request->has('tax_identification_number')) $updateData['tax_identification_number'] = $request->tax_identification_number;
        if ($request->has('payment_method')) $updateData['payment_method'] = $request->payment_method;

        if (!empty($updateData)) {
            $vendor->update($updateData);
        }

        return $this->success('Tax details updated successfully.', $vendor->only(['vat_number', 'tax_identification_number', 'payment_method']));
    }

    private function whereDetailMatchesAnyProduct($query, array $productApiIds): void
    {
        $query->where(function ($jsonQuery) use ($productApiIds) {
            if ($this->usesMysqlJsonSyntax()) {
                $jsonQuery
                    ->whereIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(provider_raw_data, '$.activityId'))"), $productApiIds)
                    ->orWhereIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(provider_raw_data, '$.productBookings[0].activityId'))"), $productApiIds);

                return;
            }

            $jsonQuery
                ->whereIn('provider_raw_data->activityId', $productApiIds)
                ->orWhereIn('provider_raw_data->productBookings->0->activityId', $productApiIds);
        });
    }

    private function usesMysqlJsonSyntax(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
