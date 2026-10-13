<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    /**
     * Display the system settings page.
     *
     * @return View
     */
    public function index(): View
    {
        $setting = SystemSetting::latest('id')->first();
        return view('backend.layouts.settings.system_settings', compact('setting'));
    }

    /**
     * Update the system settings.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title'          => 'nullable|string',
            'email'          => 'nullable|email',
            'system_name'    => 'nullable|string',
            'number'         => 'nullable|string',
            'address'        => 'nullable|string',
            'copyright_text' => 'nullable|string',
            'logo'           => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'favicon'        => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:1024',
            'description'    => 'nullable|string',
            'facebook'       => 'nullable|url',
            'twitter'        => 'nullable|url',
            'linkedin'       => 'nullable|url',
            'instagram'      => 'nullable|url',
            'whatsapp'       => 'nullable|url',
            'telegram'       => 'nullable|url',
            'supplier_guide_title' => 'nullable|string|max:255',
            'supplier_guide_description' => 'nullable|string',
            'supplier_guide_link_or_iframe' => 'nullable|string',
            'supplier_guide_media' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,mp4,webm,mov|max:51200',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $setting = SystemSetting::first() ?? new SystemSetting();

            $data = $request->except(['logo', 'favicon', 'supplier_guide_media', '_token', '_method']);
            $setting->fill($data);

            if ($request->hasFile('logo')) {
                if ($setting->logo && Storage::disk('public')->exists($setting->logo)) {
                    Storage::disk('public')->delete($setting->logo);
                }
                $logo = $request->file('logo');
                $logoName = time() . '_logo.' . $logo->getClientOriginalExtension();
                $setting->logo = $logo->storeAs('uploads/logo', $logoName, 'public');
            }

            if ($request->hasFile('favicon')) {
                if ($setting->favicon && Storage::disk('public')->exists($setting->favicon)) {
                    Storage::disk('public')->delete($setting->favicon);
                }
                $favicon = $request->file('favicon');
                $faviconName = time() . '_favicon.' . $favicon->getClientOriginalExtension();
                $setting->favicon = $favicon->storeAs('uploads/favicon', $faviconName, 'public');
            }

            if ($request->hasFile('supplier_guide_media')) {
                $supplierGuideMedia = $request->file('supplier_guide_media');
                $supplierGuideMediaName = time() . '_supplier_guide.' . $supplierGuideMedia->getClientOriginalExtension();
                $setting->supplier_guide_media = $supplierGuideMedia->storeAs('uploads/supplier-guide', $supplierGuideMediaName, 'public');
                $setting->supplier_guide_media_type = str_starts_with((string) $supplierGuideMedia->getMimeType(), 'video/')
                    ? 'video'
                    : 'image';
            }

            $setting->save();
            return back()->with('t-success', 'Updated successfully');
        } catch (Exception $e) {
            return back()->with('t-error', 'Failed to update: ' . $e->getMessage());
        }
    }
}
