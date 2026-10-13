<?php

namespace App\Repositories;

use App\Models\VendorPaymentConfirmation;
use App\Repositories\Contracts\VendorPaymentConfirmationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class VendorPaymentConfirmationRepository extends BaseRepository implements VendorPaymentConfirmationRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new VendorPaymentConfirmation();
    }

    public function getByVendor(int $vendorId): Collection
    {
        return $this->model->newQuery()
            ->where('vendor_id', $vendorId)
            ->orderByDesc('date')
            ->get();
    }
}
