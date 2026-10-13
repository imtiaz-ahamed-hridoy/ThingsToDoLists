<?php

namespace App\Services\Provider;

interface ProviderInterface
{
    /**
     * Check availability of a provider activity.
     *
     * @param string $activityApiId
     * @param string $startDate (YYYY-MM-DD)
     * @param string $endDate (YYYY-MM-DD)
     * @param string|null $currency
     * @return array
     */
    public function checkAvailability(string $activityApiId, string $startDate, string $endDate, ?string $currency = null, ?string $language = 'EN'): array;

    /**
     * Get exact raw availability response using official API payload structure.
     *
     * @param array $payload
     * @return array
     */
    public function getOfficialAvailability(array $payload): array;

    /**
     * Create a booking on the provider side.
     *
     * @param string $activityApiId
     * @param string $date (YYYY-MM-DD)
     * @param int $pax
     * @param array $customer
     * @param array $extra
     * @return array
     */
    public function createBooking(string $activityApiId, string $date, int $pax, array $customer, array $extra = []): array;

    /**
     * Get full product/activity details.
     *
     * @param string $activityApiId
     * @param string $language
     * @param string $currency
     * @return array
     */
    public function getFullProductData(string $activityApiId, string $language = 'en', string $currency = 'EUR'): array;

    /**
     * Get raw product/activity details.
     *
     * @param string $activityApiId
     * @param string $language
     * @param string $currency
     * @return array
     */
    public function getFullProductDataRaw(string $activityApiId, string $language = 'en', string $currency = 'EUR'): array;

    /**
     * Check if a product/activity has any valid (non-sold-out) availability.
     * Returns true if at least one available slot exists.
     *
     * @param string $activityApiId
     * @return bool
     */
    public function hasAvailability(string $activityApiId): bool;

    /**
     * Search for activities from the provider.
     *
     * @param array $params
     * @return array ['items' => array, 'totalCount' => int, 'page' => int, 'pageSize' => int]
     */
    public function searchActivities(array $params): array;

    /**
     * Get checkout options for a booking request.
     *
     * @param array $bookingRequest
     * @param string $currency
     * @param string $language
     * @return array
     */
    public function getCheckoutOptions(array $bookingRequest, string $currency = 'EUR', string $language = 'en'): array;

    /**
     * Submit a direct checkout (reservation) to the provider.
     *
     * @param array $payload
     * @param string $currency
     * @param string $language
     * @return array
     */
    public function submitDirectCheckout(array $payload, string $currency = 'EUR', string $language = 'en'): array;

    /**
     * Confirm a reserved booking with transaction details.
     *
     * @param string $confirmationCode
     * @param array $body
     * @return array
     */
    public function confirmReservedBooking(string $confirmationCode, array $body): array;

    /**
     * Finalize a booking after payment is confirmed.
     *
     * @param string $confirmationCode
     * @param array $confirmPayload
     * @param bool $sendCustomerNotification
     * @param string $currency
     * @param string $lang
     * @return array
     */
    public function confirmBookingAfterPayment(string $confirmationCode, array $confirmPayload, bool $sendCustomerNotification, string $currency = 'EUR', string $lang = 'EN'): array;

    /**
     * Abort a reserved booking.
     *
     * @param string $confirmationCode
     * @return array
     */
    public function abortReservedBooking(string $confirmationCode): array;
}
