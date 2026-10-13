<?php

namespace App\Repositories;

use App\Models\BookingInfo;
use App\Repositories\Contracts\BookingInfoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class BookingInfoRepository extends BaseRepository implements BookingInfoRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new BookingInfo();
    }

    public function findByReference(string $reference): ?BookingInfo
    {
        return $this->model->newQuery()->where('reference', $reference)->first();
    }

    public function findByBookingReference(string $bookingReference): ?BookingInfo
    {
        return $this->model->newQuery()->where('booking_reference', $bookingReference)->first();
    }

    public function getByUserId(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->whereNull('booking_product_id')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getByProductId(int $productId): Collection
    {
        return $this->model->newQuery()
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    public function getByVendorId(int $vendorId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('vendor_id', $vendorId)
            ->whereNull('booking_product_id')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->newQuery()
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    public function getParentBookings(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->whereNull('booking_product_id')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getRecent(int $limit = 10): Collection
    {
        return $this->model->newQuery()
            ->whereNull('booking_product_id')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getByDateRange(string $from, string $to): Collection
    {
        return $this->model->newQuery()
            ->whereNull('booking_product_id')
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get();
    }
}
