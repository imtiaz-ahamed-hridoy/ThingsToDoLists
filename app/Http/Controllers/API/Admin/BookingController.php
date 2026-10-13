<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\BookingInfo;
use App\Models\OptionPricingCategory;
use App\Models\Product;
use App\Services\Booking\BookingLifecycleService;
use App\Services\Booking\BookingPolicyService;
use App\Services\Bokun\BokunService;
use App\Services\GlobalTix\GlobalTixBookingService;
use App\Services\Payments\PaypalService;
use App\Services\Payments\StripeCheckoutService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BookingLifecycleService $lifecycle,
        private readonly BookingPolicyService $policyService,
        private readonly BokunService $bokunService,
        private readonly GlobalTixBookingService $globalTixBookingService,
        private readonly StripeCheckoutService $stripeService,
    ) {}

    public function index(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'status' => 'sometimes|string',
            'admin_status' => 'sometimes|string',
            'provider_type' => 'sometimes|string',
            'payment_gateway' => 'sometimes|string',
            'failed_only' => 'sometimes|boolean',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
            'length' => 'sometimes|integer|min:1|max:200',
            'draw' => 'sometimes|integer',
        ]);

        $query = BookingInfo::query()
            ->whereNull('booking_product_id')
            ->whereNull('event_type')
            ->with(['details', 'payment', 'product']);

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->status));
        }

        if ($request->filled('admin_status')) {
            $query->where('admin_status', $request->admin_status);
        }

        if ($request->filled('provider_type')) {
            $query->where('provider_type', strtolower((string) $request->provider_type));
        }

        if ($request->filled('payment_gateway')) {
            $query->where('payment_gateway', strtolower((string) $request->payment_gateway));
        }

        if ($request->boolean('failed_only')) {
            $query->where(function ($query): void {
                $query->where('status', 'FAILED')
                    ->orWhere('admin_status', 'needs_attention')
                    ->orWhereNotNull('failure_reason');
            });
        }

        // Handle search from both DataTable (search.value) and filter form (search)
        $searchValue = $request->input('search.value', $request->input('search', ''));
        if ($searchValue !== '' && $searchValue !== null) {
            $search = (string) $searchValue;
            $query->where(function ($query) use ($search): void {
                $query->where('booking_reference', 'LIKE', "%{$search}%")
                    ->orWhere('reference', 'LIKE', "%{$search}%")
                    ->orWhere('confirmation_code', 'LIKE', "%{$search}%")
                    ->orWhere('product_confirmation_code', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_email', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // ── Column sorting (DataTable server-side) ──────────────────────
        $sortableColumns = [
            1 => 'booking_reference',
            2 => 'title',
            3 => 'customer_name',
            4 => 'provider_type',
            5 => 'status',
            6 => 'admin_status',
            7 => 'total_price',
            8 => 'created_at',
        ];

        $orderApplied = false;
        if ($request->has('order')) {
            foreach ((array) $request->input('order', []) as $order) {
                $columnIndex = (int) ($order['column'] ?? -1);
                $direction = strtolower((string) ($order['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

                if (isset($sortableColumns[$columnIndex])) {
                    $query->orderBy($sortableColumns[$columnIndex], $direction);
                    $orderApplied = true;
                }
            }
        }

        if (! $orderApplied) {
            $query->orderByDesc('created_at');
        }

        $recordsTotal = BookingInfo::count();
        $pageSize = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = $start > 0 ? (int) ($start / $pageSize) + 1 : 1;
        $paginator = $query->paginate(max(1, min($pageSize, 200)), ['*'], 'page', $page);
        $paginator->getCollection()->transform(fn (BookingInfo $booking): array => (new BookingResource($booking))->resolve($request));

        if ($request->filled('draw')) {
            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $paginator->total(),
                'data' => $paginator->items(),
            ]);
        }

        return $this->success('Bookings retrieved successfully.', $paginator);
    }

    public function show(string $reference, Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product', 'events']);

        if (! $booking) {
            return $this->error('Booking not found.', 404);
        }

        return $this->success('Booking retrieved successfully.', (new BookingResource($booking))->resolve($request));
    }

    public function update(string $reference, Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'status' => 'sometimes|string|in:RESERVED,PAID,CONFIRMED,CANCELLED,FAILED,COMPLETED',
            'admin_status' => 'sometimes|string|in:open,needs_attention,resolved',
            'admin_note' => 'sometimes|string|max:2000',
            'failure_stage' => 'sometimes|nullable|string|max:255',
            'failure_reason' => 'sometimes|nullable|string|max:2000',
            'failure_code' => 'sometimes|nullable|string|max:255',
        ]);

        $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product']);
        if (! $booking) {
            return $this->error('Booking not found.', 404);
        }

        $updates = $this->lifecycle->filterBookingColumns($request->only([
            'status',
            'admin_status',
            'admin_note',
            'failure_stage',
            'failure_reason',
            'failure_code',
        ]));

        DB::transaction(function () use ($booking, $updates, $request): void {
            if ($updates !== []) {
                BookingInfo::where('booking_reference', $booking->resolved_reference)->update($updates);
                BookingInfo::where('reference', $booking->resolved_reference)->update($updates);
            }

            $this->lifecycle->recordEvent($booking, 'admin_updated_booking', [
                'actor_type' => 'admin',
                'actor_id' => $request->user()?->id,
                'old_status' => $booking->status,
                'new_status' => $updates['status'] ?? $booking->status,
                'message' => 'Admin updated booking.',
                'payload' => $updates,
            ]);
        });

        $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product', 'events']);

        return $this->success('Booking updated successfully.', (new BookingResource($booking))->resolve($request));
    }

    public function retry(string $reference, Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product']);
        if (! $booking) {
            return $this->error('Booking not found.', 404);
        }

        $payment = $booking->payment ?: BookingInfo::where('reference', $booking->resolved_reference)->first();
        if (! $payment || strtoupper((string) $payment->status) !== 'PAID') {
            return $this->error('Only PAID bookings that failed provider confirmation can be retried.', 409);
        }

        try {
            $provider = strtolower((string) $booking->provider_type);
            $response = $provider === 'globaltix'
                ? $this->globalTixBookingService->confirmBooking((string) $booking->confirmation_code, 'Admin retry after paid booking failure.')
                : $this->retryBokunConfirmation($booking, $payment);

            $updates = $this->lifecycle->filterBookingColumns([
                'status' => 'CONFIRMED',
                'provider_status' => 'CONFIRMED',
                'confirmed_at' => now(),
                'admin_status' => 'open',
                'failure_stage' => null,
                'failure_code' => null,
                'failure_reason' => null,
                'failed_at' => null,
                'provider_raw_data' => $response,
                'bokun_confirm_response' => $response,
            ]);

            DB::transaction(function () use ($booking, $updates, $request, $response): void {
                BookingInfo::where('booking_reference', $booking->resolved_reference)->update($updates);
                BookingInfo::where('reference', $booking->resolved_reference)->update($updates);
                $this->lifecycle->recordEvent($booking, 'admin_retry_succeeded', [
                    'actor_type' => 'admin',
                    'actor_id' => $request->user()?->id,
                    'old_status' => $booking->status,
                    'new_status' => 'CONFIRMED',
                    'message' => 'Admin retry confirmed provider booking.',
                    'payload' => ['provider_response' => $response],
                ]);
            });

            $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product', 'events']);

            return $this->success('Booking retry completed successfully.', (new BookingResource($booking))->resolve($request));
        } catch (\Throwable $e) {
            $this->lifecycle->markNeedsAttention($booking->resolved_reference, 'admin_retry', $e->getMessage(), (string) $e->getCode());

            return $this->error('Booking retry failed: ' . $e->getMessage(), 400);
        }
    }

    public function cancel(string $reference, Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'reason' => 'sometimes|string|max:1000',
            'notify' => 'sometimes|boolean',
            'timeout' => 'sometimes|boolean',
        ]);

        $booking = $this->findParentBookingByReference($reference);
        if (! $booking) {
            return $this->error('Booking not found.', 404);
        }

        $policy = $this->policyService->evaluate($booking);
        if (! $policy['can_cancel']) {
            return $this->error($policy['reason'], 409, ['can_cancel' => false]);
        }

        try {
            $providerResponse = $this->cancelProviderBooking($booking, $request);
            $updates = $this->lifecycle->filterBookingColumns([
                'status' => 'CANCELLED',
                'provider_status' => 'CANCELLED',
                'admin_status' => 'open',
                'cancelled_at' => now(),
                'cancelled_by_type' => 'admin',
                'cancelled_by_id' => $request->user()?->id,
                'cancellation_reason' => $request->input('reason', 'Cancelled by admin.'),
                'cancellation_policy_snapshot' => $policy['policy'],
                'is_cancellable_snapshot' => $policy['can_cancel'],
                'is_refundable_snapshot' => $policy['can_refund'],
            ]);

            DB::transaction(function () use ($booking, $updates, $request, $providerResponse): void {
                BookingInfo::where('booking_reference', $booking->resolved_reference)->update($updates);
                BookingInfo::where('reference', $booking->resolved_reference)->update($updates);
                $booking->details()->update(['status' => 'CANCELLED']);
                $this->lifecycle->recordEvent($booking, 'admin_cancelled_booking', [
                    'actor_type' => 'admin',
                    'actor_id' => $request->user()?->id,
                    'old_status' => $booking->status,
                    'new_status' => 'CANCELLED',
                    'message' => $updates['cancellation_reason'] ?? 'Cancelled by admin.',
                    'payload' => ['provider_response' => $providerResponse],
                ]);
            });

            $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product', 'events']);

            return $this->success('Booking cancelled successfully.', (new BookingResource($booking))->resolve($request));
        } catch (\Throwable $e) {
            $this->lifecycle->markNeedsAttention($booking->resolved_reference, 'admin_cancel', $e->getMessage(), (string) $e->getCode());

            return $this->error('Booking cancellation failed: ' . $e->getMessage(), 400);
        }
    }

    public function refund(string $reference, Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'reason' => 'sometimes|string|max:1000',
        ]);

        $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product']);
        if (! $booking) {
            return $this->error('Booking not found.', 404);
        }

        $policy = $this->policyService->evaluate($booking);
        if (! $policy['can_refund']) {
            return $this->error($policy['reason'], 409, ['can_refund' => false]);
        }

        $gateway = strtolower((string) ($booking->payment_gateway ?: $booking->payment?->payment_gateway));
        $amount = (float) $request->input('amount', $booking->total_price ?: $booking->payment?->amount);
        $currency = strtoupper((string) ($booking->currency ?: $booking->payment?->booking_currency ?: 'EUR'));
        $reason = (string) $request->input('reason', 'Refunded by admin.');

        try {
            $refundResponse = match ($gateway) {
                'stripe' => $this->stripeService->refundPaymentIntent(
                    (string) ($booking->stripe_payment_intent_id ?: $booking->payment?->stripe_payment_intent_id),
                    $amount,
                    $currency,
                    $reason,
                    $booking->resolved_reference . '-refund'
                ),
                'paypal' => app(PaypalService::class)->refundCapture(
                    (string) ($booking->paypal_capture_id ?: $booking->payment?->paypal_capture_id),
                    $amount,
                    $currency,
                    $reason,
                    $booking->resolved_reference . '-refund'
                ),
                default => throw new \RuntimeException('Unsupported refund gateway: ' . ($gateway ?: 'none')),
            };

            $refundReference = (string) data_get($refundResponse, 'id', data_get($refundResponse, 'refund.id', ''));
            $updates = $this->lifecycle->filterBookingColumns([
                'refund_status' => 'REFUNDED',
                'refund_amount' => $amount,
                'refund_currency' => $currency,
                'refund_gateway' => $gateway,
                'refund_reference' => $refundReference,
                'refund_reason' => $reason,
                'refund_requested_at' => now(),
                'refunded_at' => now(),
                'refund_raw_response' => $refundResponse,
                'admin_status' => 'open',
            ]);

            DB::transaction(function () use ($booking, $updates, $request, $refundResponse): void {
                BookingInfo::where('booking_reference', $booking->resolved_reference)->update($updates);
                BookingInfo::where('reference', $booking->resolved_reference)->update($updates);
                $this->lifecycle->recordEvent($booking, 'admin_refunded_booking', [
                    'actor_type' => 'admin',
                    'actor_id' => $request->user()?->id,
                    'message' => $updates['refund_reason'] ?? 'Refunded by admin.',
                    'payload' => ['refund_response' => $refundResponse],
                ]);
            });

            $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product', 'events']);

            return $this->success('Booking refunded successfully.', (new BookingResource($booking))->resolve($request));
        } catch (\Throwable $e) {
            $this->lifecycle->markNeedsAttention($booking->resolved_reference, 'admin_refund', $e->getMessage(), (string) $e->getCode());

            return $this->error('Booking refund failed: ' . $e->getMessage(), 400);
        }
    }

    public function resolve(string $reference, Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'admin_note' => 'sometimes|string|max:2000',
        ]);

        $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product']);
        if (! $booking) {
            return $this->error('Booking not found.', 404);
        }

        $updates = $this->lifecycle->filterBookingColumns([
            'admin_status' => 'resolved',
            'admin_note' => $request->input('admin_note', $booking->admin_note),
            'admin_resolved_at' => now(),
            'admin_resolved_by' => $request->user()?->id,
        ]);

        DB::transaction(function () use ($booking, $updates, $request): void {
            BookingInfo::where('booking_reference', $booking->resolved_reference)->update($updates);
            BookingInfo::where('reference', $booking->resolved_reference)->update($updates);
            $this->lifecycle->recordEvent($booking, 'admin_resolved_booking', [
                'actor_type' => 'admin',
                'actor_id' => $request->user()?->id,
                'message' => $updates['admin_note'] ?? 'Booking marked resolved.',
            ]);
        });

        $booking = $this->lifecycle->findByReference($reference, ['details', 'payment', 'product', 'events']);

        return $this->success('Booking marked as resolved.', (new BookingResource($booking))->resolve($request));
    }

    // ==================== ADMIN BOOKING CREATION ====================

    /**
     * Search for products to book via the admin panel.
     */
    public function searchProducts(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'provider' => 'sometimes|string|in:bokun,globaltix,all',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = $request->input('q');
        $provider = $request->input('provider', 'all');
        $limit = (int) $request->input('limit', 20);

        $products = Product::where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                    ->orWhere('product_api_id', 'LIKE', "%{$query}%")
                    ->orWhere('slug', 'LIKE', "%{$query}%")
                    ->orWhere('short_introduction', 'LIKE', "%{$query}%");
            })
            ->with(['primaryImage', 'category']);

        if ($provider !== 'all') {
            $products->where(function ($q) use ($provider) {
                $q->where('provider', $provider)
                    ->orWhere('platform', $provider);
            });
        }

        $results = $products->limit($limit)->get(['id', 'title', 'slug', 'product_api_id', 'provider', 'from_price', 'original_price', 'default_currency', 'thumbnail', 'short_introduction']);

        return $this->success('Products retrieved successfully.', $results);
    }

    /**
     * Get available ticket types / pricing categories for a product.
     */
    public function getTicketTypes(string $productId, Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $product = Product::find((int) $productId);

        if (! $product) {
            return $this->error('Product not found.', 404);
        }

        $ticketTypes = OptionPricingCategory::where('provider', $product->provider ?: $product->platform)
            ->whereHas('productOption', function ($q) use ($product): void {
                $q->where('product_id', $product->id);
            })
            ->with('productOption:id,name,external_id')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('category_label')
            ->get([
                'id',
                'product_option_id',
                'external_id',
                'category_label',
                'selling_price',
                'currency',
                'is_default',
                'min_purchase_qty',
                'max_purchase_qty',
                'age_from',
                'age_to',
                'group_size',
                'sort_order',
            ]);

        $formatted = $ticketTypes->map(function (OptionPricingCategory $cat): array {
            return [
                'id' => (int) $cat->id,
                'external_id' => $cat->external_id ? (int) $cat->external_id : null,
                'label' => $cat->category_label ?? ($cat->productOption?->name ?? 'Ticket #' . $cat->external_id),
                'price' => (float) ($cat->selling_price ?? 0),
                'currency' => $cat->currency ?? 'EUR',
                'is_default' => (bool) $cat->is_default,
                'min_qty' => (int) ($cat->min_purchase_qty ?? 1),
                'max_qty' => (int) ($cat->max_purchase_qty ?? 99),
                'age_range' => $cat->age_range,
                'group_size' => $cat->group_size,
            ];
        });

        // If no pricing categories found via options, try direct product lookup
        if ($formatted->isEmpty()) {
            $formatted = OptionPricingCategory::where('provider', $product->provider ?: $product->platform)
                ->where('external_id', (string) $product->product_api_id)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('category_label')
                ->get([
                    'id', 'external_id', 'category_label', 'selling_price',
                    'currency', 'is_default', 'min_purchase_qty', 'max_purchase_qty',
                    'age_from', 'age_to', 'group_size', 'sort_order',
                ])
                ->map(function (OptionPricingCategory $cat): array {
                    return [
                        'id' => (int) $cat->id,
                        'external_id' => $cat->external_id ? (int) $cat->external_id : null,
                        'label' => $cat->category_label ?? 'Ticket #' . $cat->external_id,
                        'price' => (float) ($cat->selling_price ?? 0),
                        'currency' => $cat->currency ?? 'EUR',
                        'is_default' => (bool) $cat->is_default,
                        'min_qty' => (int) ($cat->min_purchase_qty ?? 1),
                        'max_qty' => (int) ($cat->max_purchase_qty ?? 99),
                        'age_range' => $cat->age_range,
                        'group_size' => $cat->group_size,
                    ];
                });
        }

        $provider = $product->provider ?: $product->platform;

        return $this->success('Ticket types retrieved successfully.', [
            'product_id' => $product->id,
            'product_title' => $product->title,
            'provider' => $provider,
            'ticket_types' => $formatted,
        ]);
    }

    /**
     * Create a booking on behalf of a customer.
     * Supports Bokun and GlobalTix providers.
     */
    public function createBooking(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'provider' => 'required|string|in:bokun,globaltix',
            'product_id' => 'required|integer|exists:products,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'quantity' => 'required|integer|min:1|max:100',
            'visit_date' => 'required|date_format:Y-m-d',
            'visit_time' => 'nullable|string|max:50',
            'ticket_type_id' => 'nullable|integer',
            'booking_channel' => 'sometimes|string|max:50',
            'admin_note' => 'nullable|string|max:2000',
            'status_override' => 'sometimes|string|in:RESERVED,PAID,CONFIRMED',
            'skip_provider' => 'sometimes|boolean',
        ]);

        $product = Product::findOrFail((int) $request->input('product_id'));
        $provider = (string) $request->input('provider');
        $skipProvider = (bool) $request->boolean('skip_provider', false);

        $reference = 'ADMIN-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));

        DB::beginTransaction();

        try {
            $bookingAttributes = [
                'reference' => $reference,
                'booking_reference' => $reference,
                'confirmation_code' => $reference,
                'provider_type' => $provider,
                'product_id' => $product->id,
                'vendor_id' => $product->vendor_id,
                'user_id' => $request->user()?->id,
                'customer_name' => (string) $request->input('customer_name'),
                'customer_email' => (string) $request->input('customer_email'),
                'customer_phone' => (string) $request->input('customer_phone', ''),
                'quantity' => (int) $request->input('quantity', 1),
                'date' => (string) $request->input('visit_date'),
                'start_time' => (string) $request->input('visit_time', ''),
                'title' => $product->title,
                'total_price' => $product->from_price ?? 0,
                'currency' => $product->default_currency ?? 'EUR',
                'booking_channel' => (string) $request->input('booking_channel', 'admin'),
                'booking_source' => 'admin',
                'payment_gateway' => 'admin',
                'payment_source' => 'admin_manual',
                'admin_note' => (string) $request->input('admin_note', ''),
                'admin_status' => 'open',
            ];

            // If skip_provider is true, just create a local booking record
            if ($skipProvider) {
                $bookingAttributes['status'] = strtoupper((string) $request->input('status_override', 'RESERVED'));
            } else {
                $bookingAttributes['status'] = 'RESERVED';
            }

            $booking = BookingInfo::create($this->lifecycle->filterBookingColumns($bookingAttributes));

            // If not skipping provider, attempt to make the actual provider booking
            if (! $skipProvider) {
                try {
                    $this->processProviderBooking($booking, $product, $request);
                } catch (\Throwable $e) {
                    // Reserve failed - mark booking as failed but keep it for admin review
                    $booking->update($this->lifecycle->filterBookingColumns([
                        'status' => 'FAILED',
                        'failure_stage' => $provider . '_admin_create',
                        'failure_reason' => $e->getMessage(),
                        'failure_code' => (string) $e->getCode(),
                        'failed_at' => now(),
                        'admin_status' => 'needs_attention',
                    ]));

                    $this->lifecycle->recordEvent($booking, 'admin_create_booking_failed', [
                        'actor_type' => 'admin',
                        'actor_id' => $request->user()?->id,
                        'message' => 'Admin booking creation failed at provider: ' . $e->getMessage(),
                        'payload' => ['provider' => $provider, 'error' => $e->getMessage()],
                    ]);

                    DB::commit();

                    return $this->success('Booking created but provider reservation failed. Booking needs admin attention.', [
                        'booking' => (new BookingResource($booking->fresh(['details', 'payment', 'product', 'events'])))->resolve($request),
                        'reference' => $reference,
                        'provider_error' => $e->getMessage(),
                        'needs_attention' => true,
                    ]);
                }
            }

            // Apply status override if provided
            $statusOverride = strtoupper((string) $request->input('status_override', ''));
            if ($statusOverride !== '' && in_array($statusOverride, ['RESERVED', 'PAID', 'CONFIRMED'], true)) {
                $booking->update($this->lifecycle->filterBookingColumns([
                    'status' => $statusOverride,
                    'admin_status' => 'open',
                ]));

                if ($statusOverride === 'PAID') {
                    $booking->update($this->lifecycle->filterBookingColumns(['paid_at' => now()]));
                }
                if ($statusOverride === 'CONFIRMED') {
                    $booking->update($this->lifecycle->filterBookingColumns(['confirmed_at' => now()]));
                }
            }

            $this->lifecycle->recordEvent($booking, 'admin_created_booking', [
                'actor_type' => 'admin',
                'actor_id' => $request->user()?->id,
                'new_status' => $booking->status,
                'message' => 'Admin created booking for ' . ($request->input('customer_name')),
                'payload' => [
                    'provider' => $provider,
                    'product_id' => $product->id,
                    'product_title' => $product->title,
                    'quantity' => $request->input('quantity'),
                    'visit_date' => $request->input('visit_date'),
                    'customer_name' => $request->input('customer_name'),
                ],
            ]);

            DB::commit();

            return $this->success('Booking created successfully.', [
                'booking' => (new BookingResource($booking->fresh(['details', 'payment', 'product', 'events'])))->resolve($request),
                'reference' => $reference,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Admin booking creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'product_id' => $product->id,
                'provider' => $provider,
            ]);

            return $this->error('Failed to create booking: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate a Stripe payment link for a booking that was created via admin panel.
     */
    public function generatePaymentLink(string $reference, Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $booking = $this->lifecycle->findByReference($reference, ['product']);
        if (! $booking) {
            return $this->error('Booking not found.', 404);
        }

        $request->validate([
            'success_url' => 'sometimes|url',
            'cancel_url' => 'sometimes|url',
        ]);

        $amount = (float) ($booking->total_price ?: $booking->booking_amount ?: $booking->amount ?: 0);
        $currency = strtoupper((string) ($booking->currency ?: $booking->booking_currency ?: 'EUR'));

        if ($amount <= 0) {
            return $this->error('Booking has no payable amount. Set a price first.', 409);
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');
        $successUrl = (string) $request->input('success_url', $frontendUrl . '/payment/stripe/success');
        $cancelUrl = (string) $request->input('cancel_url', $frontendUrl . '/payment/stripe/cancel');

        $successUrl .= (str_contains($successUrl, '?') ? '&' : '?') . 'reference=' . urlencode($reference) . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl .= (str_contains($cancelUrl, '?') ? '&' : '?') . 'reference=' . urlencode($reference);

        try {
            $session = $this->stripeService->createCheckoutSession(
                $reference,
                $amount,
                $currency,
                $successUrl,
                $cancelUrl,
                [
                    'reference' => $reference,
                    'confirmation_code' => (string) $booking->confirmation_code,
                    'provider' => (string) $booking->provider_type,
                    'source' => 'admin_generated',
                ],
                'Booking - ' . ($booking->title ?? 'Payment')
            );

            $booking->update($this->lifecycle->filterBookingColumns([
                'stripe_session_id' => (string) data_get($session, 'id'),
                'stripe_checkout_response' => $session,
            ]));

            return $this->success('Payment link generated successfully.', [
                'checkout_url' => data_get($session, 'url'),
                'session_id' => data_get($session, 'id'),
                'amount' => $amount,
                'currency' => $currency,
            ]);
        } catch (\Throwable $e) {
            return $this->error('Failed to generate payment link: ' . $e->getMessage(), 500);
        }
    }

    private function processProviderBooking(BookingInfo $booking, Product $product, Request $request): void
    {
        // Resolve provider: prefer request input, then fall back to product's stored provider
        $provider = strtolower(trim((string) ($request->input('provider') ?: $product->provider ?? 'bokun')));
        $customerName = (string) $request->input('customer_name');
        $customerEmail = (string) $request->input('customer_email');
        $customerPhone = (string) $request->input('customer_phone', '');
        $visitDate = (string) $request->input('visit_date');
        $visitTime = (string) $request->input('visit_time', '');
        $quantity = (int) $request->input('quantity', 1);
        $ticketTypeId = $request->input('ticket_type_id');

        if (in_array($provider, ['manual', 'supplier'], true)) {
            $booking->update($this->lifecycle->filterBookingColumns([
                'confirmation_code' => $booking->reference,
                'status' => 'CONFIRMED',
            ]));
            return;
        }

        if ($provider === 'globaltix') {
            $productApiId = $product->product_api_id;
            $ticketTypes = [];

            if ($ticketTypeId) {
                $ticketTypes[] = [
                    'id' => (int) $ticketTypeId,
                    'quantity' => $quantity,
                    'visitDate' => $visitDate,
                    'visitTime' => $visitTime ?: '00:00:00',
                ];
            } else {
                // Try to find a default ticket type
                $pricingCategory = \App\Models\OptionPricingCategory::where('provider', 'globaltix')
                    ->whereHas('productOption', function ($q) use ($product) {
                        $q->where('product_id', $product->id);
                    })
                    ->first();

                if ($pricingCategory) {
                    $ticketTypes[] = [
                        'id' => (int) $pricingCategory->external_id,
                        'quantity' => $quantity,
                        'visitDate' => $visitDate,
                        'visitTime' => $visitTime ?: '00:00:00',
                    ];
                } else {
                    throw new \RuntimeException('No ticket type found for this GlobalTix product. Please specify a ticket_type_id.');
                }
            }

            $payload = [
                'customerName' => $customerName,
                'email' => $customerEmail,
                'mobileNumber' => $customerPhone,
                'ticketTypes' => $ticketTypes,
                'paymentMethod' => 'CREDIT',
            ];

            $response = $this->globalTixBookingService->reserveBooking($payload);

            if (! data_get($response, 'success', false)) {
                $errorMsg = data_get($response, 'error.message', data_get($response, 'error', 'GlobalTix reservation failed.'));
                throw new \RuntimeException(is_string($errorMsg) ? $errorMsg : json_encode($errorMsg));
            }

            $providerReference = (string) data_get($response, 'data.referenceNumber', '');
            $providerAmount = (float) data_get($response, 'data.amount', 0);

            $booking->update($this->lifecycle->filterBookingColumns([
                'confirmation_code' => $providerReference ?: $booking->confirmation_code,
                'booking_amount' => $providerAmount ?: $booking->booking_amount,
                'amount' => $providerAmount ?: $booking->amount,
                'provider_raw_data' => $response,
            ]));
        } elseif ($provider === 'bokun') {
            $activityBookings = [];
            $productApiId = $product->product_api_id;

            if (! $productApiId) {
                throw new \RuntimeException('Bokun product has no API ID (product_api_id).');
            }

            $activityBooking = [
                'activityId' => (int) $productApiId,
                'date' => $visitDate,
                'startTime' => $visitTime,
                'quantity' => $quantity,
                'passengers' => [
                    [
                        'pricingCategoryId' => $ticketTypeId ? (int) $ticketTypeId : 0,
                        'groupSize' => $quantity,
                        'passengerDetails' => [],
                        'answers' => [],
                        'extras' => [],
                    ],
                ],
            ];

            $activityBookings[] = $activityBooking;

            $directBooking = [
                'mainContactDetails' => [
                    ['questionId' => 'customerName', 'values' => [$customerName]],
                    ['questionId' => 'email', 'values' => [$customerEmail]],
                    ['questionId' => 'phone', 'values' => [$customerPhone]],
                ],
                'activityBookings' => $activityBookings,
                'externalBookingReference' => $booking->reference,
                'externalBookingEntityName' => config('app.name', 'Royalloge'),
                'externalBookingEntityCode' => config('app.name', 'royalloge'),
            ];

            $checkoutPayload = [
                'checkoutOption' => 'CUSTOMER_FULL_PAYMENT',
                'paymentMethod' => 'RESERVE_FOR_EXTERNAL_PAYMENT',
                'source' => 'ADMIN_BOOKING',
                'directBooking' => $directBooking,
                'sendNotificationToMainContact' => false,
                'showPricesInNotification' => false,
            ];

            $response = $this->bokunService->submitDirectCheckout($checkoutPayload, 'EUR', 'EN');

            $confirmationCode = (string) data_get($response, 'booking.confirmationCode', '');

            if ($confirmationCode === '') {
                // Check for error in response
                $errorMsg = data_get($response, 'message', data_get($response, 'error', 'Bokun booking submission failed.'));
                throw new \RuntimeException(is_string($errorMsg) ? $errorMsg : json_encode($errorMsg));
            }

            $providerAmount = (float) data_get($response, 'booking.totalDue', data_get($response, 'booking.totalPrice', 0));
            $bookingHash = (string) data_get($response, 'bookingHash', '');
            $bokunReference = $bookingHash ?: $confirmationCode;

            $productConfirmationCode = (string) data_get(
                data_get($response, 'booking.productBookings.0', data_get($response, 'booking.bookedProducts.0', [])),
                'productConfirmationCode',
                ''
            );

            $booking->update($this->lifecycle->filterBookingColumns([
                'confirmation_code' => $confirmationCode,
                'reference' => $bokunReference,
                'booking_reference' => $bokunReference,
                'booking_amount' => $providerAmount ?: $booking->booking_amount,
                'amount' => $providerAmount ?: $booking->amount,
                'total_price' => $providerAmount ?: $booking->total_price,
                'product_confirmation_code' => $productConfirmationCode ?: null,
                'bokun_submit_response' => $response,
                'provider_raw_data' => $response,
            ]));
        }
    }

    private function retryBokunConfirmation(BookingInfo $booking, BookingInfo $payment): array
    {
        $currency = strtoupper((string) ($payment->booking_currency ?: $booking->currency ?: 'EUR'));
        $amount = (float) ($payment->booking_amount ?: $payment->amount ?: $booking->total_price);
        $transactionId = (string) ($payment->stripe_payment_intent_id ?: $payment->paypal_capture_id ?: $payment->stripe_session_id);

        $this->bokunService->confirmReservedBooking((string) $booking->confirmation_code, [
            'externalBookingReference' => (string) $booking->reference,
            'externalBookingEntityName' => config('app.name', 'Royalloge'),
            'externalBookingEntityCode' => config('app.name', 'royalloge'),
            'amount' => $amount,
            'currency' => $currency,
            'transactionDetails' => [
                'transactionDate' => now()->toIso8601String(),
                'transactionId' => $transactionId,
                'cardBrand' => strtoupper((string) ($payment->payment_gateway ?: 'ADMIN_RETRY')),
                'last4' => 'N/A',
            ],
            'sendNotificationToMainContact' => true,
            'showPricesInNotification' => true,
        ]);

        return $this->bokunService->confirmBookingAfterPayment(
            (string) $booking->confirmation_code,
            [
                'externalBookingReference' => (string) $booking->reference,
                'externalBookingEntityName' => config('app.name', 'Royalloge'),
                'externalBookingEntityCode' => config('app.name', 'royalloge'),
                'bookingFields' => [
                    ['name' => 'PAYMENT_GATEWAY', 'value' => strtoupper((string) ($payment->payment_gateway ?: 'ADMIN'))],
                    ['name' => 'PAYMENT_STATUS', 'value' => 'COMPLETED'],
                ],
                'payment' => [
                    'amount' => $amount,
                    'currency' => $currency,
                    'paymentProviderType' => strtoupper((string) ($payment->payment_gateway ?: 'ADMIN')),
                    'paymentReferenceId' => $transactionId,
                    'paymentType' => 'WEB_PAYMENT',
                    'transactionDate' => now()->toIso8601String(),
                    'comment' => 'Admin retry after paid booking failure.',
                ],
            ],
            true,
            $currency,
            'EN'
        );
    }

    /**
     * Find only a parent booking (no child ticket rows, no event-log rows)
     * by any of its reference identifiers.
     */
    private function findParentBookingByReference(string $reference, array $with = ['details', 'payment', 'product']): ?BookingInfo
    {
        return BookingInfo::with($with)
            ->whereNull('booking_product_id')
            ->whereNull('event_type')
            ->where(function ($q) use ($reference): void {
                $q->where('booking_reference', $reference)
                  ->orWhere('reference', $reference)
                  ->orWhere('confirmation_code', $reference);
            })
            ->first();
    }

    private function cancelProviderBooking(BookingInfo $booking, Request $request): array
    {
        $provider = strtolower((string) $booking->provider_type);
        $status   = strtoupper((string) $booking->status);
        $reason   = (string) $request->input('reason', 'Cancelled by admin.');

        // ── GlobalTix ─────────────────────────────────────────────────────────
        if ($provider === 'globaltix') {
            $confirmationCode = (string) $booking->confirmation_code;
            if ($confirmationCode === '') {
                throw new \RuntimeException('GlobalTix booking has no confirmation code to release.');
            }

            return $this->globalTixBookingService->releaseBooking($confirmationCode, $reason);
        }

        // ── Manual / Supplier (local-only) ────────────────────────────────────
        if (in_array($provider, ['manual', 'supplier'], true)) {
            return ['success' => true, 'message' => 'Local manual/supplier booking cancelled.'];
        }

        // ── Bokun ─────────────────────────────────────────────────────────────
        if ($provider === 'bokun') {
            // The Bokun confirmation code (e.g. THI-XXXXXX) is stored in confirmation_code.
            // Fall back to booking_reference which Bokun also returns as the confirmationCode.
            $confirmationCode = (string) ($booking->confirmation_code ?: $booking->booking_reference ?: $booking->reference);

            if ($confirmationCode === '') {
                throw new \RuntimeException('Bokun booking has no confirmation code. Cannot cancel.');
            }

            // RESERVED → use abort-reserved (no payment captured, safe abort)
            if ($status === 'RESERVED') {
                $timeout  = $request->boolean('timeout', false);
                $response = $this->bokunService->abortReservedBooking($confirmationCode, $timeout);

                // Bokun abort-reserved returns an empty body on success (HTTP 200/204).
                // Treat a non-false response (even empty array) as success.
                if (! isset($response['success']) || $response['success'] !== false) {
                    return array_merge(['success' => true], $response);
                }

                $errorMsg = $response['message'] ?? $response['error'] ?? 'Bokun abort-reserved failed.';
                throw new \RuntimeException((string) $errorMsg);
            }

            // CONFIRMED / PAID → use cancel endpoints
            // Prefer product-level cancellation (more granular) over booking-level.
            if ($booking->product_confirmation_code) {
                return $this->bokunService->cancelProductBooking((string) $booking->product_confirmation_code, [
                    'note'   => $reason,
                    'notify' => $request->boolean('notify', true),
                    'refund' => false,
                    'refundAmount' => 0,
                ]);
            }

            return $this->bokunService->cancelBooking($confirmationCode, [
                'note'   => $reason,
                'notify' => $request->boolean('notify', true),
                'refund' => false,
            ]);
        }

        return ['success' => true, 'message' => 'Local supplier booking cancelled.'];
    }

    private function isAdmin(Request $request): bool
    {
        $role = strtolower((string) ($request->user()?->role ?? ''));

        return in_array($role, ['admin', 'administrator', 'super-admin', 'superadmin'], true);
    }
}
