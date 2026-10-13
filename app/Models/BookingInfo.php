<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};

/**
 * Unified BookingInfo model — single source of truth for all booking records.
 *
 * The booking_info table uses a parent-child relationship:
 *   • booking_product_id IS NULL → parent booking rows
 *   • booking_product_id NOT NULL AND event_type IS NULL → line-item / child rows
 *   • event_type NOT NULL → audit-trail rows
 *
 * @property int|null $user_id
 * @property int|null $product_id
 * @property int|null $vendor_id
 * @property string|null $reference
 * @property string|null $booking_reference
 * @property string|null $confirmation_code
 * @property string|null $provider_type
 * @property string $status
 * @property string|null $admin_status
 */
class BookingInfo extends Model
{
    use SoftDeletes;

    protected $table = 'booking_info';

    // ────────────────────────────────────────────────────────────────────────
    //  Fillable Attributes
    // ────────────────────────────────────────────────────────────────────────

    protected $fillable = [
        // Booking identifiers
        'reference',
        'booking_reference',
        'confirmation_code',

        // Provider identifiers
        'booking_id',
        'product_booking_id',
        'product_confirmation_code',
        'provider_type',
        'provider_status',

        // FKs to core entities
        'user_id',
        'product_id',
        'vendor_id',
        'booking_channel',
        'booking_source',

        // Self-referencing (parent/child)
        'booking_product_id',
        'booking_payment_id',
        'event_type',

        // Customer snapshot
        'customer_name',
        'customer_email',
        'customer_phone',

        // Status & financial
        'status',
        'total_price',
        'currency',
        'payer_name',
        'payer_email',
        'booking_amount',
        'booking_currency',
        'paypal_currency',
        'amount',
        'paid_at',
        'confirmed_at',

        // Payment gateway
        'payment_gateway',
        'payment_source',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'stripe_last_webhook_event_id',
        'paypal_payer_id',
        'paypal_order_id',
        'paypal_capture_id',

        // Detail columns (child rows)
        'type',
        'title',
        'price',
        'date',
        'start_time',
        'quantity',

        // Provider payload
        'e_ticket_url',
        'globaltix_tickets',
        'provider_raw_data',
        'bokun_raw_data',
        'bokun_submit_response',
        'bokun_confirm_response',
        'paypal_create_response',
        'paypal_capture_response',
        'stripe_checkout_response',

        // Failure
        'failure_stage',
        'failure_reason',
        'failure_code',
        'failed_at',

        // Admin
        'admin_status',
        'admin_note',
        'admin_resolved_at',
        'admin_resolved_by',

        // Cancellation
        'cancel_requested_at',
        'cancelled_at',
        'cancelled_by_type',
        'cancelled_by_id',
        'cancellation_reason',
        'cancellation_policy_snapshot',
        'is_cancellable_snapshot',
        'is_refundable_snapshot',

        // Refund
        'refund_status',
        'refund_amount',
        'refund_currency',
        'refund_gateway',
        'refund_reference',
        'refund_reason',
        'refund_requested_at',
        'refunded_at',
        'refund_raw_response',

        // Event logs
        'events_log',
    ];

    // ────────────────────────────────────────────────────────────────────────
    //  Casts
    // ────────────────────────────────────────────────────────────────────────

    protected $casts = [
        // Financial
        'total_price' => 'decimal:2',
        'booking_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'price' => 'decimal:2',
        'refund_amount' => 'decimal:2',

        // JSON
        'provider_raw_data' => 'array',
        'globaltix_tickets' => 'array',
        'bokun_submit_response' => 'array',
        'bokun_confirm_response' => 'array',
        'paypal_create_response' => 'array',
        'paypal_capture_response' => 'array',
        'stripe_checkout_response' => 'array',
        'cancellation_policy_snapshot' => 'array',
        'refund_raw_response' => 'array',
        'events_log' => 'array',

        // Booleans
        'is_cancellable_snapshot' => 'boolean',
        'is_refundable_snapshot' => 'boolean',

        // Dates
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
        'admin_resolved_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refund_requested_at' => 'datetime',
        'refunded_at' => 'datetime',
        'date' => 'date',
    ];

    // ────────────────────────────────────────────────────────────────────────
    //  Accessors & Mutators
    // ────────────────────────────────────────────────────────────────────────

    public function getBokunRawDataAttribute()
    {
        return $this->provider_raw_data;
    }

    public function setBokunRawDataAttribute($value): void
    {
        $this->provider_raw_data = $value;
    }

    // ────────────────────────────────────────────────────────────────────────
    //  Relationships
    // ────────────────────────────────────────────────────────────────────────

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BookingInfo::class, 'booking_product_id')->whereNull('event_type');
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BookingInfo::class, 'booking_payment_id');
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BookingInfo::class, 'booking_product_id')->whereNotNull('event_type');
    }


    // ────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────────

    public function getResolvedReferenceAttribute(): ?string
    {
        return $this->booking_reference ?: $this->reference;
    }
}
