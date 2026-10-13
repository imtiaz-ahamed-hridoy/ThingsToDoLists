<?php

namespace App\Repositories;

use App\Models\VendorInvoice;
use App\Repositories\Contracts\VendorInvoiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class VendorInvoiceRepository extends BaseRepository implements VendorInvoiceRepositoryInterface
{
    protected function resolveModel(): Model
    {
        return new VendorInvoice();
    }

    public function getByVendor(int $vendorId): Collection
    {
        return $this->model->newQuery()
            ->where('vendor_id', $vendorId)
            ->orderByDesc('date')
            ->get();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model->newQuery()
            ->where('status', $status)
            ->orderByDesc('date')
            ->get();
    }
}
