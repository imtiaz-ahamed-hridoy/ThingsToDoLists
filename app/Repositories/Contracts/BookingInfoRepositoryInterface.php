<?php

namespace App\Repositories\Contracts;

use App\Models\BookingInfo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BookingInfoRepositoryInterface extends RepositoryInterface
{
    public function findByReference(string $reference): ?BookingInfo;

    public function findByBookingReference(string $bookingReference): ?BookingInfo;

    public function getByUserId(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function getByProductId(int $productId): Collection;

    public function getByVendorId(int $vendorId, int $perPage = 15): LengthAwarePaginator;

    public function getByStatus(string $status): Collection;

    public function getParentBookings(int $perPage = 15): LengthAwarePaginator;

    public function getRecent(int $limit = 10): Collection;

    public function getByDateRange(string $from, string $to): Collection;
}
