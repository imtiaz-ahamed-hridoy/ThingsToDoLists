<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ┌─────────────────────────────────────────────────────────────────┐
     * │  BOOKING INFO — Single table for all booking-related records.  │
     * │                                                                 │
     * │  1. A parent-child relationship utilizing `booking_product_id`: │
     * │  • booking_product_id IS NULL → parent booking rows            │
     * │  • booking_product_id NOT NULL AND event_type IS NULL → detail rows│
     * │  • event_type NOT NULL → audit-trail rows                      │
     * └─────────────────────────────────────────────────────────────────┘
     */
    public function up(): void
    {
        if (!Schema::hasTable('booking_info')) {
            Schema::create('booking_info', function (Blueprint $table) {
                // ── 1. IDs ──────────────────────────────────────────────────────
                $table->id();

                // ── 2. Booking identifiers ──────────────────────────────────────
                $table->string('reference')->nullable()->unique();
                $table->string('booking_reference')->nullable()->unique();
                $table->string('confirmation_code')->nullable()->index();

                // ── 4. Provider identifiers ─────────────────────────────────────
                $table->string('booking_id')->nullable()->index();
                $table->string('product_booking_id')->nullable()->index();
                $table->string('product_confirmation_code')->nullable()->index();
                $table->string('provider_type')->nullable()->index();

                // ── 5. FKs to core entities ─────────────────────────────────────
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('vendor_id')->nullable()->index();
                $table->string('booking_channel', 50)->nullable()->index();
                $table->string('booking_source', 50)->nullable()->index();

                // ── 5.5 Parent-Child Relations ──────────────────────────────────
                $table->unsignedBigInteger('booking_product_id')->nullable()->index();
                $table->unsignedBigInteger('booking_payment_id')->nullable()->index();
                $table->string('event_type')->nullable()->index();

                // ── 6. Customer snapshot ────────────────────────────────────────
                $table->string('customer_name')->nullable();
                $table->string('customer_email')->nullable()->index();
                $table->string('customer_phone')->nullable()->index();

                // ── 7. Lifecycle statuses ───────────────────────────────────────
                $table->string('status')->default('RESERVED')->index();
                $table->string('provider_status')->nullable()->index();

                // ── 8. Financial columns ────────────────────────────────────────
                $table->decimal('total_price', 12, 2)->default(0);
                $table->string('currency', 3)->default('USD')->index();
                $table->string('payer_name')->nullable();
                $table->string('payer_email')->nullable()->index();
                $table->decimal('booking_amount', 12, 2)->nullable();
                $table->string('booking_currency', 3)->nullable()->index();
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('paypal_currency', 3)->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();

                // ── 9. Payment gateway columns ──────────────────────────────────
                $table->string('payment_gateway')->nullable()->index();
                $table->string('payment_source')->nullable()->index();
                $table->string('stripe_session_id')->nullable()->index();
                $table->string('stripe_payment_intent_id')->nullable()->index();
                $table->string('stripe_last_webhook_event_id')->nullable()->index();
                $table->string('paypal_payer_id')->nullable();
                $table->string('paypal_order_id')->nullable()->index();
                $table->string('paypal_capture_id')->nullable()->index();

                // ── 10. Detail columns (used by child rows) ──────────────────────
                $table->string('type')->default('activity')->index();
                $table->string('title')->nullable();
                $table->decimal('price', 12, 2)->default(0);
                $table->date('date')->nullable()->index();
                $table->time('start_time')->nullable();
                $table->integer('quantity')->default(1);

                // ── 11. Provider payload & ticket artifacts ────────────────────
                $table->string('e_ticket_url')->nullable();
                $table->json('globaltix_tickets')->nullable();
                $table->json('provider_raw_data')->nullable();
                $table->json('bokun_submit_response')->nullable();
                $table->json('bokun_confirm_response')->nullable();
                $table->json('paypal_create_response')->nullable();
                $table->json('paypal_capture_response')->nullable();
                $table->json('stripe_checkout_response')->nullable();

                // ── Events Log ────────────────────────────────────────────────
                $table->json('events_log')->nullable()->comment('Stores array of events/audit trail logs');

                // ── Failure Handling ──────────────────────────────────────────
                $table->string('failure_stage')->nullable()->index();
                $table->text('failure_reason')->nullable();
                $table->string('failure_code')->nullable();
                $table->timestamp('failed_at')->nullable()->index();

                // ── 13. Admin columns ──────────────────────────────────────────
                $table->string('admin_status', 50)->default('open')->index();
                $table->text('admin_note')->nullable();
                $table->timestamp('admin_resolved_at')->nullable();
                $table->unsignedBigInteger('admin_resolved_by')->nullable()->index();

                // ── 14. Cancellation columns ───────────────────────────────────
                $table->timestamp('cancel_requested_at')->nullable();
                $table->timestamp('cancelled_at')->nullable()->index();
                $table->string('cancelled_by_type', 50)->nullable();
                $table->unsignedBigInteger('cancelled_by_id')->nullable()->index();
                $table->text('cancellation_reason')->nullable();
                $table->json('cancellation_policy_snapshot')->nullable();
                $table->boolean('is_cancellable_snapshot')->nullable()->index();
                $table->boolean('is_refundable_snapshot')->nullable()->index();

                // ── 15. Refund columns ─────────────────────────────────────────
                $table->string('refund_status', 50)->nullable()->index();
                $table->decimal('refund_amount', 12, 2)->nullable();
                $table->string('refund_currency', 3)->nullable()->index();
                $table->string('refund_gateway', 50)->nullable()->index();
                $table->string('refund_reference')->nullable()->index();
                $table->text('refund_reason')->nullable();
                $table->timestamp('refund_requested_at')->nullable();
                $table->timestamp('refunded_at')->nullable()->index();
                $table->json('refund_raw_response')->nullable();

                // ── 17. Timestamps & soft-delete ───────────────────────────────
                $table->timestamps();
                $table->softDeletes();

                // ── Composite indexes ─────────────────────────────────────────

                $table->index(['provider_type', 'status']);
                $table->index(['confirmation_code', 'status']);
                $table->index(['reference', 'status']);
                $table->index(['booking_id', 'product_booking_id']);

                // Performance indexes
                $table->index('created_at');
                $table->index(['product_id', 'created_at'], 'booking_info_product_created_index');
                $table->index(['vendor_id', 'status', 'created_at'], 'booking_info_vendor_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_info');
    }
};
