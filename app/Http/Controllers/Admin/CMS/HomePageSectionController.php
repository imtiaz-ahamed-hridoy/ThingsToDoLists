<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\HomePageSection\ReorderHomePageSectionsRequest;
use App\Http\Requests\HomePageSection\UpdateHomePageSectionRequest;
use App\Models\HomePageSection;
use App\Models\Product;
use App\Support\ManualSelectionOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomePageSectionController extends Controller
{
    /**
     * Display a listing of home page sections.
     */
    public function index()
    {
        $sections = HomePageSection::with('locations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        return view('backend.layouts.cmsSettings.sections.index', compact('sections'));
    }



    /**
     * Show the form for editing the specified section.
     */
    public function edit($id)
    {
        $section = HomePageSection::findOrFail($id);
        $selectedProductIds = old('product_ids', $section->product_ids ?? []);
        $products = ManualSelectionOrder::sortModelsByIds(
            Product::orderBy('title')->get(),
            $selectedProductIds
        );

        if ($section->section_type === 'locations') {
            $section->load('locations');
        }

        return view('backend.layouts.cmsSettings.sections.edit', compact('section', 'products'));
    }

    /**
     * AJAX: Search cities or countries for Select2 dropdown.
     * Supports ?type=city&q=Dubai or ?type=country&q=UAE
     */
    public function locationSearch(Request $request)
    {
        $type  = $request->input('type');
        $query = trim($request->input('q', ''));

        if ($type === 'city') {
            $results = \App\Models\City::select('id', 'city_name', 'country_name')
                ->when($query, fn ($q) => $q->where(function ($q) use ($query) {
                    $q->where('city_name', 'like', "%{$query}%")
                      ->orWhere('country_name', 'like', "%{$query}%");
                }))
                ->orderBy('city_name')
                ->get()
                ->map(fn ($c) => [
                    'id'   => $c->id,
                    'text' => $c->city_name . ($c->country_name ? ' (' . $c->country_name . ')' : ''),
                ]);
        } elseif ($type === 'country') {
            $results = \App\Models\City::select('country_name')
                ->whereNotNull('country_name')
                ->where('country_name', '!=', '')
                ->when($query, fn ($q) => $q->where('country_name', 'like', "%{$query}%"))
                ->groupBy('country_name')
                ->orderBy('country_name')
                ->get()
                ->map(fn ($c) => [
                    'id'   => $c->country_name,
                    'text' => $c->country_name,
                ]);
        } else {
            $results = collect();
        }

        return response()->json(['results' => $results->values()]);
    }

    /**
     * Update the specified section.
     */
    public function update(UpdateHomePageSectionRequest $request, $id)
    {

        $section = HomePageSection::findOrFail($id);
        $section->title = $request->title;
        $section->short_description = $request->short_description;
        $section->status = $request->boolean('status', true);

        if ($section->section_type === 'locations') {
            // Delete removed locations
            $existingLocationIds = $section->locations()->pluck('id')->toArray();
            $requestedLocationIds = collect($request->locations ?? [])->pluck('id')->filter()->toArray();
            $locationsToDelete = array_diff($existingLocationIds, $requestedLocationIds);
            
            foreach($locationsToDelete as $locId) {
                $loc = \App\Models\HomePageSectionLocation::find($locId);
                if ($loc && $loc->getRawOriginal('image') && Storage::disk('public')->exists($loc->getRawOriginal('image'))) {
                    Storage::disk('public')->delete($loc->getRawOriginal('image'));
                }
                if ($loc) {
                    $loc->delete();
                }
            }

            foreach($request->locations ?? [] as $index => $locData) {
                if (empty($locData['location_type']) || empty($locData['location_id'])) {
                    continue;
                }
                $loc = null;
                if (!empty($locData['id'])) {
                    $loc = \App\Models\HomePageSectionLocation::find($locData['id']);
                }
                if (!$loc) {
                    $loc = new \App\Models\HomePageSectionLocation();
                    $loc->home_page_section_id = $section->id;
                }
                $loc->location_type = $locData['location_type'];
                $loc->location_id = $locData['location_id'];

                if (isset($locData['image']) && $locData['image'] instanceof \Illuminate\Http\UploadedFile) {
                     if ($loc->getRawOriginal('image') && Storage::disk('public')->exists($loc->getRawOriginal('image'))) {
                         Storage::disk('public')->delete($loc->getRawOriginal('image'));
                     }
                     $loc->image = $locData['image']->store('cms/home_page/sections/locations', 'public');
                }
                $loc->save();
            }
        } else {
            $section->product_ids = ManualSelectionOrder::ids($request->product_ids ?? []);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($section->getRawOriginal('image') && Storage::disk('public')->exists($section->getRawOriginal('image'))) {
                Storage::disk('public')->delete($section->getRawOriginal('image'));
            }
            $section->image = $request->file('image')->store('cms/home_page/sections', 'public');
        }

        $section->save();

        return redirect()->route('cmsSettings.sections.index')
            ->with('success', 'Home Page Section Updated Successfully');
    }



    /**
     * Update sort order via drag & drop.
     */
    public function reorder(ReorderHomePageSectionsRequest $request)
    {

        foreach ($request->orders as $item) {
            HomePageSection::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            \Illuminate\Support\Facades\Cache::forget('homepage:section:' . $item['id']);
        }

        \Illuminate\Support\Facades\Cache::forget('homepage:sections:active');

        return response()->json(['message' => 'Order updated successfully']);
    }

    /**
     * Toggle section status.
     */
    public function toggleStatus($id)
    {
        $section = HomePageSection::findOrFail($id);
        $section->status = !$section->status;
        $section->save();

        return redirect()->back()->with('success', 'Section status updated successfully');
    }
}
