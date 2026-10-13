<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MailSettingController extends Controller
{
    /**
     * Display mail settings page.
     *
     * @return View
     */
    public function index(): View
    {
        $settings = [
            'mail_mailer'       => $this->getEnv('MAIL_MAILER', ''),
            'mail_host'         => $this->getEnv('MAIL_HOST', ''),
            'mail_port'         => $this->getEnv('MAIL_PORT', ''),
            'mail_username'     => $this->getEnv('MAIL_USERNAME', ''),
            'mail_password'     => $this->getEnv('MAIL_PASSWORD', ''),
            'mail_encryption'   => $this->getEnv('MAIL_ENCRYPTION', ''),
            'mail_from_address' => $this->getEnv('MAIL_FROM_ADDRESS', ''),
        ];

        return view('backend.layouts.settings.mail_settings', compact('settings'));
    }

    /**
     * Update mail settings.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'mail_mailer'       => 'nullable|string',
            'mail_host'         => 'nullable|string',
            'mail_port'         => 'nullable|string',
            'mail_username'     => 'nullable|string',
            'mail_password'     => 'nullable|string',
            'mail_encryption'   => 'nullable|string',
            'mail_from_address' => 'nullable|string',
        ]);

        try {
            $data = [
                'MAIL_MAILER'       => $request->mail_mailer,
                'MAIL_HOST'         => $request->mail_host,
                'MAIL_PORT'         => $request->mail_port,
                'MAIL_USERNAME'     => $request->mail_username,
                'MAIL_PASSWORD'     => $request->mail_password,
                'MAIL_ENCRYPTION'   => $request->mail_encryption,
                'MAIL_FROM_ADDRESS' => $request->mail_from_address,
            ];

            $this->setEnv($data);

            // Clear config cache so new values take effect in production
            Artisan::call('config:clear');

            return back()->with('t-success', 'Updated successfully');
        } catch (Exception) {
            return back()->with('t-error', 'Failed to update');
        }
    }

    private function getEnv(string $key, $default = '')
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return $default;
        }

        $envContent = file_get_contents($envPath);
        $pattern = "/^{$key}=(.*)$/m";
        if (preg_match($pattern, $envContent, $matches)) {
            $val = trim($matches[1]);
            return trim($val, '"\''); // strip quotes if any
        }
        
        return $default;
    }

    private function setEnv(array $data)
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $formattedValue = (str_contains($value, ' ') || str_contains($value, '$')) ? '"' . $value . '"' : $value;

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$formattedValue}", $envContent);
            } else {
                $envContent .= "\n{$key}={$formattedValue}";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}

