<?php

namespace App\Repositories\Contracts;

use App\Models\VendorInvoice;
use Illuminate\Database\Eloquent\Collection;

interface VendorInvoiceRepositoryInterface extends RepositoryInterface
{
    public function getByVendor(int $vendorId): Collection;

    public function getByStatus(string $status): Collection;
}
