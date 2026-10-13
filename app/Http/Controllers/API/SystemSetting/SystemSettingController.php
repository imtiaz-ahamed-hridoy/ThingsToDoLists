<?php

namespace App\Http\Controllers\API\SystemSetting;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SystemSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private SystemSettingRepositoryInterface $systemSettingRepository
    ) {}

    /**
     * Get system settings (cached as array, 1 hour).
     */
    public function systemSetting()
    {
        $cacheKey = 'system_setting:global';

        $systemSetting = Cache::remember($cacheKey, now()->addMinutes(20), function () {
            $setting = $this->systemSettingRepository->first();
            return $setting ? $setting->toArray() : null;
        });

        if ($systemSetting) {
            return $this->ok('Data Retrieve Successfully!', $systemSetting, 200);
        }

        return $this->error('System Setting not found. Please seed the system_settings table.', 404);
    }
}
