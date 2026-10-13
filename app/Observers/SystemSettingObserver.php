<?php

namespace App\Observers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingObserver
{
    /**
     * Handle the SystemSetting "saved" event.
     */
    public function saved(SystemSetting $systemSetting): void
    {
        $this->clearCache();
    }

    /**
     * Handle the SystemSetting "deleted" event.
     */
    public function deleted(SystemSetting $systemSetting): void
    {
        $this->clearCache();
    }

    /**
     * Clear public API cache immediately.
     */
    private function clearCache(): void
    {
        Cache::forget('system_setting:global');
    }
}
