<?php

namespace App\Services\Booking;

use App\Models\BookingInfo;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class BookingPolicyService
{
    public function evaluate(BookingInfo $booking): array
    {
        $product = $this->resolveProduct($booking);
        $policy = $product?->cancellationPolicies()->first();
        $rawPolicyData = null;
        $isRefundable = false;

        // Extract raw policy data from provider response if local DB has no policy
        if (!$policy && strtolower((string) $booking->provider_type) === 'bokun') {
            foreach ($booking->details as $detail) {
                // Check if activityBooking level isRefundable exists
                if (data_get($detail->provider_raw_data, 'isRefundable')) {
                    $isRefundable = true;
                }

                $rawPolicyData = data_get($detail->provider_raw_data, 'product.cancellationPolicy')
                    ?? data_get($detail->provider_raw_data, 'activity.cancellationPolicy')
                    ?? data_get($detail->provider_raw_data, 'activity.rates.0.cancellationPolicy');

                if ($rawPolicyData) {
                    break;
                }
            }

            // Also check main booking's bokun_confirm_response or raw data if detail didn't have it
            if (!$rawPolicyData && data_get($booking->provider_raw_data, 'activityBookings.0.cancellationPolicy')) {
                $rawPolicyData = data_get($booking->provider_raw_data, 'activityBookings.0.cancellationPolicy');
            }
            if (data_get($booking->provider_raw_data, 'activityBookings.0.isRefundable')) {
                $isRefundable = true;
            }
        }

        $productAllowsCancellation = (bool) ($product?->is_cancellable ?? false);
        $policyAllowsCancellation = (bool) ($policy?->is_cancellable ?? false);

        $isRawPolicyCancellable = false;
        if ($rawPolicyData) {
            $type = data_get($rawPolicyData, 'policyTypeEnum', data_get($rawPolicyData, 'policyType'));
            $isRawPolicyCancellable = strtoupper((string) $type) !== 'NON_REFUNDABLE';
        }

        // Bokun RESERVED bookings can always be aborted via the abort-reserved endpoint,
        // regardless of product cancellation policy — no payment has been captured yet.
        $provider = strtolower((string) $booking->provider_type);
        $bookingStatus = strtoupper((string) $booking->status);
        $isBokunReserved = ($provider === 'bokun') && ($bookingStatus === 'RESERVED');

        $canCancel = $isBokunReserved
            || $productAllowsCancellation
            || $policyAllowsCancellation
            || $isRawPolicyCancellable
            || $isRefundable;

        $gateway = strtolower((string) ($booking->payment_gateway ?: $booking->payment?->payment_gateway));
        $hasCapturedPayment = match ($gateway) {
            'stripe' => (bool) ($booking->stripe_payment_intent_id ?: $booking->payment?->stripe_payment_intent_id),
            'paypal' => (bool) ($booking->paypal_capture_id ?: $booking->payment?->paypal_capture_id),
            default => false,
        };
        $refundableStatus = in_array(strtoupper((string) $booking->status), ['PAID', 'CONFIRMED', 'CANCELLED'], true);

        $policyData = null;
        if ($policy) {
            $policyData = [
                'id' => $policy->id,
                'provider' => $policy->provider,
                'title' => $policy->title,
                'policy' => $policy->policy,
                'policyType' => $policy->policyType,
                'is_cancellable' => (bool) $policy->is_cancellable,
                'penalty_rules' => $policy->penalty_rules,
                'meta' => $policy->meta,
            ];
        } elseif ($rawPolicyData) {
            $policyData = [
                'id' => data_get($rawPolicyData, 'id'),
                'provider' => 'bokun',
                'title' => data_get($rawPolicyData, 'title', 'Provider Policy'),
                'policy' => null,
                'policyType' => data_get($rawPolicyData, 'policyTypeEnum', data_get($rawPolicyData, 'policyType')),
                'is_cancellable' => $isRawPolicyCancellable,
                'penalty_rules' => data_get($rawPolicyData, 'penaltyRules', []),
                'meta' => $rawPolicyData,
            ];
        }

        return [
            'can_cancel' => $canCancel,
            'can_refund' => $canCancel && $hasCapturedPayment && $refundableStatus,
            'reason' => $canCancel
                ? ($isBokunReserved
                    ? 'Bokun RESERVED booking can be aborted via abort-reserved (no payment captured).'
                    : 'Cancellation policy allows cancellation/refund.')
                : 'This booking is not cancellable because the linked product has no cancellable policy.',
            'product_id' => $product?->id,
            'vendor_id'  => $product?->vendor_id,
            'policy'     => $policyData,
        ];
    }

    public function resolveProduct(BookingInfo $booking): ?Product
    {
        if ($booking->relationLoaded('product') && $booking->product) {
            return $booking->product;
        }

        if ($booking->product_id) {
            $product = Product::find($booking->product_id);
            if ($product) {
                return $product;
            }
        }

        $provider = strtolower((string) $booking->provider_type);
        foreach ($booking->details as $detail) {
            if ($detail->product_id) {
                $product = Product::find($detail->product_id);
                if ($product) {
                    return $product;
                }
            }

            $externalId = data_get($detail->provider_raw_data, 'activityId')
                ?? data_get($detail->provider_raw_data, 'productBookings.0.activityId')
                ?? data_get($detail->provider_raw_data, 'product.id')
                ?? data_get($detail->provider_raw_data, 'ticketType.productId');

            if ($externalId) {
                $query = Product::where('product_api_id', (string) $externalId);
                if ($provider !== '') {
                    $query->where(function (Builder $query) use ($provider): void {
                        $query->where('provider', $provider)->orWhere('platform', $provider);
                    });
                }

                $product = $query->first();

                if ($product) {
                    return $product;
                }
            }
        }

        return null;
    }
}
