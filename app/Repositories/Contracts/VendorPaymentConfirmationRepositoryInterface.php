<?php

namespace App\Repositories\Contracts;

use App\Models\VendorPaymentConfirmation;
use Illuminate\Database\Eloquent\Collection;

interface VendorPaymentConfirmationRepositoryInterface extends RepositoryInterface
{
    public function getByVendor(int $vendorId): Collection;
}
