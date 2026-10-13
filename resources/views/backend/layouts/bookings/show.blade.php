@extends('backend.app')

@section('title', 'Booking: ' . ($booking->booking_reference ?? $booking->reference))

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Booking Details</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('booking.index') }}">Bookings</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $booking->booking_reference ?? $booking->reference }}</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}

    <div class="row">
        {{-- LEFT COLUMN: Overview & Customer --}}
        <div class="col-xl-8 col-lg-7">
            {{-- OVERVIEW CARD --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">
                        <i class="fa fa-info-circle"></i> Overview
                    </h3>
                    <span>
                        @php
                            $stColors = ['RESERVED'=>'bg-secondary','PAID'=>'bg-primary','CONFIRMED'=>'bg-success','CANCELLED'=>'bg-danger','FAILED'=>'bg-danger','COMPLETED'=>'bg-info'];
                            $adColors = ['open'=>'bg-success','needs_attention'=>'bg-warning text-dark','resolved'=>'bg-info'];
                        @endphp
                        <span class="badge {{ $stColors[$booking->status] ?? 'bg-secondary' }}">{{ $booking->status ?? '—' }}</span>
                        <span class="badge {{ $adColors[$booking->admin_status ?? 'open'] ?? 'bg-secondary' }}">{{ $booking->admin_status ?? 'open' }}</span>
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td style="width:180px"><strong>Reference:</strong></td>
                                    <td><code>{{ $booking->booking_reference ?: $booking->reference }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Confirmation Code:</strong></td>
                                    <td><code>{{ $booking->confirmation_code ?? '—' }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Booking ID:</strong></td>
                                    <td>{{ $booking->booking_id ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Product Booking ID:</strong></td>
                                    <td>{{ $booking->product_booking_id ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Product Confirmation Code:</strong></td>
                                    <td><code>{{ $booking->product_confirmation_code ?? '—' }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Provider:</strong></td>
                                    <td>
                                        @php
                                            $providerLabels = ['bokun' => 'Bokun', 'globaltix' => 'GlobalTix', 'supplier' => 'Supplier'];
                                        @endphp
                                        <span class="badge bg-{{ $booking->provider_type === 'globaltix' ? 'info' : ($booking->provider_type === 'bokun' ? 'primary' : 'secondary') }}">
                                            {{ $providerLabels[$booking->provider_type] ?? ucfirst($booking->provider_type) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Provider Status:</strong></td>
                                    <td>{{ $booking->provider_status ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Channel:</strong></td>
                                    <td>{{ $booking->booking_channel ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Source:</strong></td>
                                    <td>{{ $booking->booking_source ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ optional($booking->created_at)->format('M j, Y g:i A') ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Updated:</strong></td>
                                    <td>{{ optional($booking->updated_at)->format('M j, Y g:i A') ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- CUSTOMER CARD --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fa fa-user"></i> Customer</h3>
                    @if($booking->customer_email && $customerBookings->isNotEmpty())
                        <a href="{{ route('booking.index') }}?search={{ urlencode($booking->customer_email) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="fa fa-external-link"></i> All by {{ $booking->customer_email }}
                        </a>
                    @endif
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td style="width:180px"><strong>Name:</strong></td>
                                    <td>{{ $booking->customer_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>
                                        @if($booking->customer_email)
                                            <a href="mailto:{{ $booking->customer_email }}">{{ $booking->customer_email }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td>
                                        @if($booking->customer_phone)
                                            <a href="tel:{{ $booking->customer_phone }}">{{ $booking->customer_phone }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- CUSTOMER BOOKING HISTORY --}}
                @if($customerBookings->isNotEmpty())
                    <div class="card-footer bg-light p-0">
                        <div class="p-2 px-3 border-bottom bg-white">
                            <small class="text-muted fw-bold"><i class="fa fa-history"></i> Booking History ({{ $customerBookings->count() }} more)</small>
                        </div>
                        <div style="max-height: 350px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="small">
                                    <tr>
                                        <th class="ps-3">Ref</th>
                                        <th>Product</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th class="pe-3">View</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    @foreach($customerBookings as $cb)
                                        @php
                                            $stCol = $stColors[$cb->status] ?? 'bg-secondary';
                                        @endphp
                                        <tr>
                                            <td class="ps-3"><code>{{ $cb->booking_reference ?: $cb->reference }}</code></td>
                                            <td>{{ Str::limit($cb->product?->title ?? $cb->title ?? '—', 35) }}</td>
                                            <td><span class="badge {{ $stCol }}">{{ $cb->status ?? '—' }}</span></td>
                                            <td>{{ ($cb->currency ?? 'EUR') . ' ' . number_format((float) ($cb->total_price ?? 0), 2) }}</td>
                                            <td>{{ optional($cb->created_at)->format('M j, Y') ?? '—' }}</td>
                                            <td class="pe-3">
                                                <a href="{{ route('booking.show', $cb->booking_reference ?: $cb->reference) }}" class="btn btn-sm btn-outline-info py-0 px-1" target="_blank">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- PRODUCT CARD --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-cube"></i> Product</h3>
                </div>
                <div class="card-body">
                    @if($booking->relationLoaded('product') && $booking->product)
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td style="width:180px"><strong>ID:</strong></td>
                                        <td>{{ $booking->product->id }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Title:</strong></td>
                                        <td>{{ $booking->product->title ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>API ID:</strong></td>
                                        <td>{{ $booking->product->product_api_id ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Vendor ID:</strong></td>
                                        <td>{{ $booking->product->vendor_id ?? ($booking->vendor_id ?? '—') }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Price:</strong></td>
                                        <td>{{ ($booking->product->default_currency ?? 'EUR') . ' ' . number_format((float) ($booking->product->from_price ?? 0), 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted">
                            <p class="mb-1"><strong>Product ID:</strong> {{ $booking->product_id ?? '—' }}</p>
                            <p class="mb-1"><strong>Title:</strong> {{ $booking->title ?? '—' }}</p>
                            <p class="mb-0"><strong>Vendor ID:</strong> {{ $booking->vendor_id ?? '—' }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- BOOKING ITEMS CARD --}}
            @if($booking->relationLoaded('details') && $booking->details->isNotEmpty())
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fa fa-list"></i> Booking Items</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($booking->details as $index => $detail)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $detail->title ?? 'Item ' . ($index + 1) }}</td>
                                            <td>{{ $detail->type ?? '—' }}</td>
                                            <td>{{ optional($detail->date)->format('M j, Y') ?? $detail->date ?? '—' }}</td>
                                            <td>{{ $detail->start_time ?? '—' }}</td>
                                            <td>{{ $detail->quantity ?? 1 }}</td>
                                            <td>{{ ($detail->currency ?? 'EUR') . ' ' . number_format((float) ($detail->price ?? 0), 2) }}</td>
                                            <td><span class="badge {{ $stColors[$detail->status] ?? 'bg-secondary' }}">{{ $detail->status ?? '—' }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ACTIVITY TIMELINE CARD — unified booking events + activity logs --}}
            @php
                $timelineIcons = [
                    'admin_created_booking' => 'fa-plus-circle text-success',
                    'admin_updated_booking' => 'fa-pencil text-primary',
                    'admin_retry_succeeded' => 'fa-refresh text-success',
                    'admin_cancelled_booking' => 'fa-ban text-danger',
                    'admin_refunded_booking' => 'fa-undo text-warning',
                    'admin_resolved_booking' => 'fa-check-circle text-info',
                    'booking_failed' => 'fa-exclamation-circle text-danger',
                    'admin_attention_required' => 'fa-exclamation-triangle text-warning',
                    'admin_create_booking_failed' => 'fa-times-circle text-danger',
                    'created' => 'fa-plus-circle text-success',
                    'updated' => 'fa-pencil text-primary',
                    'deleted' => 'fa-trash text-danger',
                    'default' => 'fa-info-circle text-secondary',
                ];

                // Merge booking events + activity logs into a unified timeline, sorted by created_at desc
                $timeline = collect();

                // Booking events from the booking_info table (child rows with event_type)
                if ($booking->relationLoaded('events')) {
                    foreach ($booking->events as $ev) {
                        $timeline->push([
                            'source' => 'booking_event',
                            'created_at' => $ev->created_at ?? $ev->updated_at ?? now(),
                            'type' => $ev->event_type,
                            'message' => $ev->message ?? ucfirst(str_replace('_', ' ', $ev->event_type)),
                            'actor_type' => $ev->actor_type ?? 'system',
                            'actor_name' => $ev->actor_type === 'admin' ? 'Admin #' . $ev->actor_id : ($ev->actor_type ?? 'System'),
                            'details' => null,
                        ]);
                    }
                }

                // Activity logs from the Spatie activity_log table
                foreach ($activityLogs as $log) {
                    $causer = $log->causer;
                    $actorName = $causer ? ($causer->name ?? $causer->email ?? 'User #' . $log->causer_id) : ($log->causer_type ?? 'System');
                    $props = is_array($log->properties) ? $log->properties : (is_string($log->properties) ? json_decode($log->properties, true) ?: [] : []);
                    $attrs = $props['attributes'] ?? [];
                    $old = $props['old'] ?? [];

                    // Build detail string for changed attributes
                    $changeDetails = null;
                    if (! empty($old) && ! empty($attrs)) {
                        $changes = [];
                        foreach ($attrs as $key => $newVal) {
                            if (isset($old[$key]) && $old[$key] !== $newVal) {
                                $oldVal = is_scalar($old[$key]) ? $old[$key] : json_encode($old[$key]);
                                $newValStr = is_scalar($newVal) ? $newVal : json_encode($newVal);
                                $changes[] = "<code>{$key}</code>: " . e((string) $oldVal) . ' → ' . e((string) $newValStr);
                            }
                        }
                        if ($changes) {
                            $changeDetails = implode('<br>', array_slice($changes, 0, 5));
                            if (count($changes) > 5) {
                                $changeDetails .= '<br><em class="text-muted">… and ' . (count($changes) - 5) . ' more</em>';
                            }
                        }
                    }

                    $timeline->push([
                        'source' => 'activity_log',
                        'created_at' => $log->created_at ?? now(),
                        'type' => $log->event ?? $log->description,
                        'message' => $log->description,
                        'actor_type' => $log->causer_type ?? 'system',
                        'actor_name' => $actorName,
                        'details' => $changeDetails,
                        'ip' => $props['ip'] ?? $log->ip_address ?? null,
                    ]);
                }

                $timeline = $timeline->sortByDesc('created_at')->values();
            @endphp

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="fa fa-clock-o"></i> Activity Timeline</h3>
                    <span class="badge bg-secondary">{{ $timeline->count() }} events</span>
                </div>
                <div class="card-body">
                    @if($timeline->isEmpty())
                        <div class="text-center py-4 text-muted">
                            <i class="fa fa-inbox fa-3x mb-2"></i>
                            <p class="mb-0">No activity recorded for this booking yet.</p>
                            <small>Actions like creating, editing, or cancelling will appear here.</small>
                        </div>
                    @else
                        <div style="max-height: 500px; overflow-y: auto;">
                            {{-- Timeline vertical line --}}
                            <div class="position-relative" style="padding-left: 40px;">
                                <div class="position-absolute" style="left: 18px; top: 0; bottom: 0; width: 2px; background: #e9ecef;"></div>

                                @foreach($timeline as $item)
                                    @php
                                        $icon = $timelineIcons[$item['type']] ?? $timelineIcons['default'];
                                        $iconColor = explode(' ', $icon)[1] ?? 'text-secondary';
                                        $iconBase = explode(' ', $icon)[0] ?? 'fa-info-circle';
                                    @endphp
                                    <div class="mb-4 position-relative">
                                        {{-- Icon on the timeline line --}}
                                        <div class="position-absolute rounded-circle bg-white d-flex align-items-center justify-content-center"
                                             style="left: -32px; width: 32px; height: 32px; border: 2px solid #dee2e6;">
                                            <i class="fa {{ $iconBase }} {{ $iconColor }} fa-fw" style="font-size: 0.85rem;"></i>
                                        </div>

                                        {{-- Content card --}}
                                        <div class="card card-body py-2 px-3" style="border-left: 3px solid {{ match(true) { str_contains($iconColor, 'success') => '#28a745', str_contains($iconColor, 'danger') => '#dc3545', str_contains($iconColor, 'warning') => '#ffc107', str_contains($iconColor, 'info') => '#17a2b8', default => '#6c757d' } }};">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <strong>{{ $item['message'] }}</strong>
                                                <small class="text-muted text-nowrap ms-2">{{ optional($item['created_at'])->format('M j, Y g:i A') }}</small>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <small class="text-muted">
                                                    <i class="fa fa-user-circle"></i> {{ $item['actor_name'] }}
                                                    @if($item['source'] === 'activity_log' && !empty($item['ip']))
                                                        &middot; <i class="fa fa-globe"></i> {{ $item['ip'] }}
                                                    @endif
                                                </small>
                                                @if($item['source'] === 'activity_log')
                                                    <small class="text-muted"><i class="fa fa-database"></i> activity_log</small>
                                                @else
                                                    <small class="text-muted"><i class="fa fa-calendar"></i> booking_event</small>
                                                @endif
                                            </div>
                                            @if($item['details'])
                                                <div class="mt-2 p-2 bg-light rounded small" style="font-size: 0.8rem;">
                                                    <strong>Changed:</strong><br>
                                                    {!! $item['details'] !!}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN: Pricing, Payment, Admin, Failure, Cancellation, Refund --}}
        <div class="col-xl-4 col-lg-5">
            {{-- PRICING CARD --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-money"></i> Pricing</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td><strong>Total:</strong></td>
                                    <td class="text-end">{{ ($booking->currency ?? 'EUR') . ' ' . number_format((float) ($booking->total_price ?? 0), 2) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Booking Amt:</strong></td>
                                    <td class="text-end">{{ ($booking->booking_currency ?? 'EUR') . ' ' . number_format((float) ($booking->booking_amount ?? 0), 2) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Amount:</strong></td>
                                    <td class="text-end">{{ ($booking->currency ?? 'EUR') . ' ' . number_format((float) ($booking->amount ?? 0), 2) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Quantity:</strong></td>
                                    <td class="text-end">{{ $booking->quantity ?? 1 }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- PAYMENT CARD --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-credit-card"></i> Payment</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td><strong>Gateway:</strong></td>
                                    <td class="text-end">{{ $booking->payment_gateway ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Source:</strong></td>
                                    <td class="text-end">{{ $booking->payment_source ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Paid At:</strong></td>
                                    <td class="text-end">{{ optional($booking->paid_at)->format('M j, Y g:i A') ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Confirmed At:</strong></td>
                                    <td class="text-end">{{ optional($booking->confirmed_at)->format('M j, Y g:i A') ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Stripe Session:</strong></td>
                                    <td class="text-end" style="word-break: break-all;"><code>{{ $booking->stripe_session_id ?? '—' }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Stripe PI:</strong></td>
                                    <td class="text-end" style="word-break: break-all;"><code>{{ $booking->stripe_payment_intent_id ?? '—' }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>PayPal Order:</strong></td>
                                    <td class="text-end"><code>{{ $booking->paypal_order_id ?? '—' }}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>PayPal Capture:</strong></td>
                                    <td class="text-end"><code>{{ $booking->paypal_capture_id ?? '—' }}</code></td>
                                </tr>
                                @if($booking->e_ticket_url)
                                <tr>
                                    <td><strong>E-Ticket:</strong></td>
                                    <td class="text-end">
                                        <a href="{{ $booking->e_ticket_url }}" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fa fa-file-pdf-o"></i> View
                                        </a>
                                    </td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- CANCELLATION POLICY CARD --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-gavel"></i> Cancellation Policy</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span><strong>Can Cancel:</strong></span>
                        <span>{!! $policy['can_cancel'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' !!}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span><strong>Can Refund:</strong></span>
                        <span>{!! $policy['can_refund'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' !!}</span>
                    </div>
                    @if($policy['reason'])
                        <p class="mb-0 text-muted small">{{ $policy['reason'] }}</p>
                    @endif
                    @if($policy['policy'])
                        <hr>
                        <h6>Policy Details:</h6>
                        <pre class="small mb-0" style="max-height: 150px; overflow-y: auto;">{{ json_encode($policy['policy'], JSON_PRETTY_PRINT) }}</pre>
                    @endif
                </div>
            </div>

            {{-- FAILURE CARD --}}
            @if($booking->failure_stage || $booking->failure_reason)
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h3 class="card-title"><i class="fa fa-exclamation-circle"></i> Failure Info</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    @if($booking->failure_stage)
                                    <tr>
                                        <td><strong>Stage:</strong></td>
                                        <td class="text-end"><code>{{ $booking->failure_stage }}</code></td>
                                    </tr>
                                    @endif
                                    @if($booking->failure_code)
                                    <tr>
                                        <td><strong>Code:</strong></td>
                                        <td class="text-end"><code>{{ $booking->failure_code }}</code></td>
                                    </tr>
                                    @endif
                                    @if($booking->failure_reason)
                                    <tr>
                                        <td><strong>Reason:</strong></td>
                                        <td class="text-end" style="word-break: break-word;">{{ $booking->failure_reason }}</td>
                                    </tr>
                                    @endif
                                    @if($booking->failed_at)
                                    <tr>
                                        <td><strong>Failed At:</strong></td>
                                        <td class="text-end">{{ optional($booking->failed_at)->format('M j, Y g:i A') }}</td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- CANCELLATION INFO CARD --}}
            @if($booking->cancelled_at)
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h3 class="card-title"><i class="fa fa-ban"></i> Cancellation</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td><strong>Cancelled At:</strong></td>
                                        <td class="text-end">{{ optional($booking->cancelled_at)->format('M j, Y g:i A') }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>By:</strong></td>
                                        <td class="text-end">{{ $booking->cancelled_by_type ?? '—' }} {{ $booking->cancelled_by_id ? '(ID: ' . $booking->cancelled_by_id . ')' : '' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reason:</strong></td>
                                        <td class="text-end" style="word-break: break-word;">{{ $booking->cancellation_reason ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- REFUND INFO CARD --}}
            @if($booking->refund_status)
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h3 class="card-title"><i class="fa fa-undo"></i> Refund</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td class="text-end">{{ $booking->refund_status }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Amount:</strong></td>
                                        <td class="text-end">{{ ($booking->refund_currency ?? 'EUR') . ' ' . number_format((float) ($booking->refund_amount ?? 0), 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Gateway:</strong></td>
                                        <td class="text-end">{{ $booking->refund_gateway ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reference:</strong></td>
                                        <td class="text-end"><code>{{ $booking->refund_reference ?? '—' }}</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reason:</strong></td>
                                        <td class="text-end" style="word-break: break-word;">{{ $booking->refund_reason ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Refunded At:</strong></td>
                                        <td class="text-end">{{ optional($booking->refunded_at)->format('M j, Y g:i A') ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ADMIN CARD --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-cog"></i> Admin</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td class="text-end"><span class="badge {{ $adColors[$booking->admin_status ?? 'open'] ?? 'bg-secondary' }}">{{ $booking->admin_status ?? 'open' }}</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Note:</strong></td>
                                    <td class="text-end" style="word-break: break-word;">{{ $booking->admin_note ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Resolved At:</strong></td>
                                    <td class="text-end">{{ optional($booking->admin_resolved_at)->format('M j, Y g:i A') ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ACTION BUTTONS --}}
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex gap-2 flex-wrap">
                    <a href="{{ route('booking.index') }}" class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Bookings
                    </a>

                    <a href="{{ route('booking.invoice', $booking->booking_reference ?: $booking->reference) }}" class="btn btn-outline-dark" target="_blank">
                        <i class="fa fa-file-pdf-o"></i> Print Invoice
                    </a>

                    <button type="button" class="btn btn-primary" onclick="openEditFromShow('{{ $booking->booking_reference ?: $booking->reference }}')">
                        <i class="fa fa-pencil"></i> Edit
                    </button>

                    @if(strtoupper($booking->status ?? '') === 'PAID')
                        <button type="button" class="btn btn-success" onclick="openRetryFromShow('{{ $booking->booking_reference ?: $booking->reference }}')">
                            <i class="fa fa-refresh"></i> Retry Confirmation
                        </button>
                    @endif

                    @if($policy['can_cancel'])
                        <button type="button" class="btn btn-danger" onclick="openCancelFromShow('{{ $booking->booking_reference ?: $booking->reference }}')">
                            <i class="fa fa-ban"></i> Cancel Booking
                        </button>
                    @endif

                    @if($policy['can_refund'])
                        <button type="button" class="btn btn-warning text-dark" onclick="openRefundFromShow('{{ $booking->booking_reference ?: $booking->reference }}')">
                            <i class="fa fa-undo"></i> Refund
                        </button>
                    @endif

                    @if(($booking->admin_status ?? 'open') === 'needs_attention')
                        <button type="button" class="btn btn-info" onclick="openResolveFromShow('{{ $booking->booking_reference ?: $booking->reference }}')">
                            <i class="fa fa-check-circle"></i> Mark Resolved
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- INCLUDE MODALS (reuse existing modals from bookings index) --}}
    {{-- EDIT MODAL --}}
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="editReference">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="editStatus" class="form-control">
                                    <option value="">— No change —</option>
                                    <option value="RESERVED">RESERVED</option>
                                    <option value="PAID">PAID</option>
                                    <option value="CONFIRMED">CONFIRMED</option>
                                    <option value="CANCELLED">CANCELLED</option>
                                    <option value="FAILED">FAILED</option>
                                    <option value="COMPLETED">COMPLETED</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Admin Status</label>
                                <select name="admin_status" id="editAdminStatus" class="form-control">
                                    <option value="">— No change —</option>
                                    <option value="open">Open</option>
                                    <option value="needs_attention">Needs Attention</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Failure Stage</label>
                                <input type="text" name="failure_stage" id="editFailureStage" class="form-control" placeholder="e.g. provider_confirm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Failure Code</label>
                                <input type="text" name="failure_code" id="editFailureCode" class="form-control" placeholder="e.g. TIMEOUT">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Failure Reason</label>
                                <textarea name="failure_reason" id="editFailureReason" class="form-control" rows="2" placeholder="Describe the failure reason..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Admin Note</label>
                                <textarea name="admin_note" id="editAdminNote" class="form-control" rows="3" placeholder="Internal note about this booking..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editSubmitBtn">Update Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- CANCEL MODAL --}}
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelModalLabel">Cancel Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="cancelForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="cancelReference">
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> Are you sure you want to cancel this booking? This will attempt to cancel with the provider.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" id="cancelReason" class="form-control" rows="3" required placeholder="Required: why is this booking being cancelled?"></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="notify" value="1" class="form-check-input" id="cancelNotify" checked>
                            <label class="form-check-label" for="cancelNotify">Notify customer</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="timeout" value="1" class="form-check-input" id="cancelTimeout">
                            <label class="form-check-label" for="cancelTimeout">Use timeout (Bokun reserved bookings)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep it</button>
                        <button type="submit" class="btn btn-danger" id="cancelSubmitBtn">
                            <i class="fa fa-ban"></i> Yes, Cancel Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- RETRY MODAL --}}
    <div class="modal fade" id="retryModal" tabindex="-1" aria-labelledby="retryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="retryModalLabel">Retry Booking Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="retryForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="retryReference">
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> This will attempt to re-confirm a PAID booking with the provider.
                        </div>
                        <p class="mb-0"><strong>Reference:</strong> <span id="retryReferenceDisplay"></span></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="retrySubmitBtn">
                            <i class="fa fa-refresh"></i> Retry Confirmation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- REFUND MODAL --}}
    <div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="refundModalLabel">Refund Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="refundForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="refundReference">
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> This will process a refund via the payment gateway (Stripe/PayPal).
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Refund Amount</label>
                            <div class="input-group">
                                <span class="input-group-text" id="refundCurrency">USD</span>
                                <input type="number" name="amount" id="refundAmount" class="form-control" step="0.01" min="0.01" placeholder="Full or partial amount">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Refund Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" id="refundReason" class="form-control" rows="3" required placeholder="Required: reason for refund"></textarea>
                        </div>
                        <p class="mb-0 text-muted small">Gateway: <strong id="refundGatewayDisplay">—</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark" id="refundSubmitBtn">
                            <i class="fa fa-undo"></i> Process Refund
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- RESOLVE MODAL --}}
    <div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="resolveModalLabel">Resolve Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="resolveForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="resolveReference">
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> Mark this booking as resolved.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Resolution Note</label>
                            <textarea name="admin_note" id="resolveNote" class="form-control" rows="3" placeholder="Optional note..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info text-white" id="resolveSubmitBtn">
                            <i class="fa fa-check-circle"></i> Mark Resolved
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                }
            });

            // Helper: status badge
            function statusBadge(status) {
                var map = {
                    'RESERVED': 'bg-secondary',
                    'PAID': 'bg-primary',
                    'CONFIRMED': 'bg-success',
                    'CANCELLED': 'bg-danger',
                    'FAILED': 'bg-danger',
                    'COMPLETED': 'bg-info',
                };
                var cls = map[status] || 'bg-secondary';
                return '<span class="badge ' + cls + '">' + (status || '—') + '</span>';
            }

            function adminStatusBadge(status) {
                var map = {
                    'open': 'bg-success',
                    'needs_attention': 'bg-warning text-dark',
                    'resolved': 'bg-info',
                };
                var cls = map[status] || 'bg-secondary';
                return '<span class="badge ' + cls + '">' + (status || 'open') + '</span>';
            }

            // ==================== EDIT ====================
            window.openEditFromShow = function(reference) {
                $('#editReference').val(reference);
                $('#editStatus').val('');
                $('#editAdminStatus').val('');
                $('#editFailureStage').val('');
                $('#editFailureCode').val('');
                $('#editFailureReason').val('');
                $('#editAdminNote').val('');
                $('#editModalLabel').text('Edit Booking: ' + reference);

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(reference),
                    type: 'GET',
                    success: function(resp) {
                        var booking = resp.data || resp;
                        $('#editFailureStage').val(booking.failure_stage || '');
                        $('#editFailureCode').val(booking.failure_code || '');
                        $('#editFailureReason').val(booking.failure_reason || '');
                    }
                });

                $('#editSubmitBtn').prop('disabled', false).html('Update Booking');
                $('#editModal').modal('show');
            };

            $('#editForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#editReference').val();
                var btn = $('#editSubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');

                var data = {};
                var status = $('#editStatus').val();
                var adminStatus = $('#editAdminStatus').val();
                if (status) data.status = status;
                if (adminStatus) data.admin_status = adminStatus;
                data.failure_stage = $('#editFailureStage').val() || null;
                data.failure_code = $('#editFailureCode').val() || null;
                data.failure_reason = $('#editFailureReason').val() || null;
                data.admin_note = $('#editAdminNote').val() || null;

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref),
                    type: 'PUT',
                    data: data,
                    success: function(resp) {
                        toastr.success('Booking updated successfully.');
                        $('#editModal').modal('hide');
                        location.reload();
                    },
                    error: function(xhr) {
                        var msg = 'Update failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        }
                        toastr.error(msg);
                        btn.prop('disabled', false).html('Update Booking');
                    }
                });
            });

            // ==================== RETRY ====================
            window.openRetryFromShow = function(reference) {
                $('#retryReference').val(reference);
                $('#retryReferenceDisplay').text(reference);
                $('#retrySubmitBtn').prop('disabled', false).html('<i class="fa fa-refresh"></i> Retry Confirmation');
                $('#retryModal').modal('show');
            };

            $('#retryForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#retryReference').val();
                var btn = $('#retrySubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Retrying...');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref) + "/retry",
                    type: 'POST',
                    success: function(resp) {
                        toastr.success('Booking retry completed successfully!');
                        $('#retryModal').modal('hide');
                        location.reload();
                    },
                    error: function(xhr) {
                        var msg = 'Retry failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        toastr.error(msg);
                        btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Retry Confirmation');
                    }
                });
            });

            // ==================== CANCEL ====================
            window.openCancelFromShow = function(reference) {
                $('#cancelReference').val(reference);
                $('#cancelReason').val('');
                $('#cancelNotify').prop('checked', true);
                $('#cancelTimeout').prop('checked', false);
                $('#cancelSubmitBtn').prop('disabled', false).html('<i class="fa fa-ban"></i> Yes, Cancel Booking');
                $('#cancelModal').modal('show');
            };

            $('#cancelForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#cancelReference').val();
                var reason = $('#cancelReason').val();
                if (!reason.trim()) {
                    toastr.warning('Please provide a cancellation reason.');
                    return;
                }
                var btn = $('#cancelSubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Cancelling...');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref) + "/cancel",
                    type: 'POST',
                    data: {
                        reason: reason,
                        notify: $('#cancelNotify').is(':checked') ? '1' : '0',
                        timeout: $('#cancelTimeout').is(':checked') ? '1' : '0',
                    },
                    success: function(resp) {
                        toastr.success('Booking cancelled successfully.');
                        $('#cancelModal').modal('hide');
                        location.reload();
                    },
                    error: function(xhr) {
                        var msg = 'Cancellation failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        toastr.error(msg);
                        btn.prop('disabled', false).html('<i class="fa fa-ban"></i> Yes, Cancel Booking');
                    }
                });
            });

            // ==================== REFUND ====================
            window.openRefundFromShow = function(reference) {
                $('#refundReference').val(reference);
                $('#refundAmount').val('');
                $('#refundReason').val('');
                $('#refundGatewayDisplay').text('—');
                $('#refundCurrency').text('USD');
                $('#refundSubmitBtn').prop('disabled', false).html('<i class="fa fa-undo"></i> Process Refund');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(reference),
                    type: 'GET',
                    success: function(resp) {
                        var booking = resp.data || resp;
                        var total = booking.amount?.total !== null ? parseFloat(booking.amount?.total).toFixed(2) : '';
                        var cur = booking.amount?.currency || 'USD';
                        var gateway = booking.payment?.gateway || '—';
                        $('#refundAmount').val(total);
                        $('#refundCurrency').text(cur);
                        $('#refundGatewayDisplay').text(gateway);
                    }
                });

                $('#refundModal').modal('show');
            };

            $('#refundForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#refundReference').val();
                var amount = $('#refundAmount').val();
                var reason = $('#refundReason').val();
                if (!reason.trim()) {
                    toastr.warning('Please provide a refund reason.');
                    return;
                }
                var btn = $('#refundSubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing Refund...');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref) + "/refund",
                    type: 'POST',
                    data: { amount: amount, reason: reason },
                    success: function(resp) {
                        toastr.success('Refund processed successfully.');
                        $('#refundModal').modal('hide');
                        location.reload();
                    },
                    error: function(xhr) {
                        var msg = 'Refund failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        toastr.error(msg);
                        btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Process Refund');
                    }
                });
            });

            // ==================== RESOLVE ====================
            window.openResolveFromShow = function(reference) {
                $('#resolveReference').val(reference);
                $('#resolveNote').val('');
                $('#resolveSubmitBtn').prop('disabled', false).html('<i class="fa fa-check-circle"></i> Mark Resolved');
                $('#resolveModal').modal('show');
            };

            $('#resolveForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#resolveReference').val();
                var note = $('#resolveNote').val();
                var btn = $('#resolveSubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Resolving...');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref) + "/resolve",
                    type: 'POST',
                    data: { admin_note: note || null },
                    success: function(resp) {
                        toastr.success('Booking marked as resolved.');
                        $('#resolveModal').modal('hide');
                        location.reload();
                    },
                    error: function(xhr) {
                        var msg = 'Failed to resolve booking.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        toastr.error(msg);
                        btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Mark Resolved');
                    }
                });
            });

            // Reset modal buttons on hide
            $('.modal').on('hidden.bs.modal', function() {
                $(this).find('button[type="submit"]').prop('disabled', false);
            });
        });
    </script>
@endpush
