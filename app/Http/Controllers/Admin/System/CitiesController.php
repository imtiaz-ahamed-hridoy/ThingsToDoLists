<?php

namespace App\Http\Controllers\Admin\System;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Http\Requests\City\StoreCityRequest;
use App\Http\Requests\City\UpdateCityRequest;
use App\Models\City;
use Yajra\DataTables\Facades\DataTables;
use App\Traits\AllTraits;

class CitiesController extends Controller
{

    use AllTraits;
    /**
     * Display a listing of the cities.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = City::latest()->get();
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function ($data) {
                    return '
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="' . route('cities.edit', $data->id) . '" class="btn btn-primary" title="Edit">
                                <i class="fe fe-edit"></i>
                            </a>
                            <a href="#" onclick="showDeleteConfirm(' . $data->id . ')" class="btn btn-danger" title="Delete">
                                <i class="fe fe-trash"></i>
                            </a>
                        </div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('backend.layouts.cities.index');
    }

    /**
     * Show the form for creating a new city.
     */
    public function create()
    {
        return view('backend.layouts.cities.create');
    }

    /**
     * Store a newly created city in storage.
     */
    public function store(StoreCityRequest $request)
    {

        try {
            // Upload thumbnail
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail_image')) {
                $file = $request->file('thumbnail_image');
                $filename = time() . '_thumbnail_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $thumbnailPath = $file->storeAs('uploads/cities/thumbnail', $filename, 'public');
            }

            // Upload gallery images
            $galleryPaths = [];
            if ($request->hasFile('gallery_image')) {
                foreach ($request->file('gallery_image') as $image) {
                    $filename = time() . '_gallery_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $galleryPaths[] = $image->storeAs('uploads/cities/gallery', $filename, 'public');
                }
            }

            // Save city
            City::create([
                'city_name'          => $request->city_name,
                'city_short_code'    => $request->city_short_code ?? null,
                'city_is_capital'    => $request->city_is_capital ?? 0,
                'slug'               => $request->slug,
                'country_name'       => $request->country_name ?? null,
                'country_short_code' => $request->country_short_code ?? null,
                'country_slug'       => $request->country_slug ?? null,
                'currency_code'      => $request->currency_code ?? null,
                'mobile_prefix'      => $request->mobile_prefix ?? null,
                'title'              => $request->title ?? null,
                'short_description'  => $request->short_description ?? null,
                'thumbnail_image'    => $thumbnailPath,
                'gallery_image'      => json_encode($galleryPaths),
                'item_status'        => $request->item_status,
            ]);

            return redirect()->route('cities.index')->with('t-success', 'City created successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('t-error', 'City creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing a city.
     */
    public function edit(string $id)
    {
        $data = City::findOrFail($id);
        return view('backend.layouts.cities.edit', compact('data'));
    }

    public function update(UpdateCityRequest $request, string $id)
    {
        $data = City::findOrFail($id);

        try {
            if ($request->hasFile('thumbnail_image')) {
                $paths = $this->updateToStorage($data, 'thumbnail_image', $request->file('thumbnail_image'), 'uploads/cities/thumbnail');
                $data->thumbnail_image = $paths[0];
            }

            if ($request->hasFile('gallery_image')) {
                $paths = $this->updateToStorage($data, 'gallery_image', $request->file('gallery_image'), 'uploads/cities/gallery');
                $data->gallery_image = json_encode($paths);
            }

            $data->update($request->only([
                'city_name',
                'slug',
                'city_short_code',
                'city_is_capital',
                'country_name',
                'country_short_code',
                'country_slug',
                'currency_code',
                'mobile_prefix',
                'title',
                'short_description',
                'item_status'
            ]));

            return redirect()->route('cities.index')->with('t-success', 'City updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('t-error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $data = City::findOrFail($id);

            if ($data->thumbnail_image && Storage::disk('public')->exists($data->thumbnail_image)) {
                Storage::disk('public')->delete($data->thumbnail_image);
            }

            $gallery = json_decode($data->gallery_image ?? '[]', true);
            foreach ($gallery as $img) {
                if (Storage::disk('public')->exists($img)) {
                    Storage::disk('public')->delete($img);
                }
            }

            $data->delete();

            return response()->json(['status' => 'success', 'message' => 'City deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }
}

