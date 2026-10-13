<?php

namespace App\Services\GlobalTix;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GlobalTixBookingService extends GlobalTixBaseService
{
    /**
     * Reserve a booking
     *
     * @param array $payload Full reservation payload
     * @return array
     * @throws ConnectionException
     */
    public function reserveBooking(array $payload): array
    {
        return $this->request('POST', '/api/booking/reserve', $payload);
    }

    /**
     * Confirm a booking
     *
     * @param string $referenceNumber
     * @return array
     * @throws ConnectionException
     */
    public function confirmBooking(string $referenceNumber, string $remarks = ''): array
    {
        return $this->request('POST', '/api/booking/confirm', [
            'referenceNumber' => $referenceNumber,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Release a booking
     *
     * @param string $referenceNumber
     * @param string $remarks
     * @return array
     * @throws ConnectionException
     */
    public function releaseBooking(string $referenceNumber, string $remarks = ''): array
    {
        return $this->request('POST', '/api/booking/release', [
            'referenceNumber' => $referenceNumber,
            'remarks' => $remarks,
        ]);
    }

    /**
     * Get booking details
     *
     * @param string $referenceNumber
     * @param string|null $partnerReferenceNumber
     * @return array
     * @throws ConnectionException
     */
    public function getBookingDetails(string $referenceNumber, ?string $partnerReferenceNumber = null): array
    {
        $query = ['referenceNumber' => $referenceNumber];
        if ($partnerReferenceNumber) {
            $query['partnerReferenceNumber'] = $partnerReferenceNumber;
        }

        return $this->request('GET', '/api/booking/details', $query);
    }
}
