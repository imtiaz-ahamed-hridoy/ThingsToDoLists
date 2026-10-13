<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeatureType\StoreFeatureTypeRequest;
use App\Http\Requests\FeatureType\UpdateFeatureTypeRequest;
use App\Models\FeatureType;
use App\Models\Product;
use App\Support\ManualSelectionOrder;
use Illuminate\Http\Request;

class FeatureTypeController extends Controller
{
    public function index()
    {
        $types = FeatureType::orderBy('sort_order')->get();
        return view('backend.layouts.cmsSettings.feature_types.index', compact('types'));
    }

    public function create()
    {
        $products = Product::orderBy('title')->limit(200)->get();
        return view('backend.layouts.cmsSettings.feature_types.create', compact('products'));
    }

    public function store(StoreFeatureTypeRequest $request)
    {

        $type = FeatureType::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'status' => $request->boolean('status', true),
            'sort_order' => (FeatureType::max('sort_order') ?? 0) + 1,
        ]);

        if ($request->filled('product_ids')) {
            $type->products()->sync(ManualSelectionOrder::syncPayload($request->product_ids));
        }

        return redirect()->route('cmsSettings.featureTypes.index')->with('success', 'Feature type created');
    }

    public function edit($id)
    {
        $type = FeatureType::with('products:id')->findOrFail($id);
        $selectedProductIds = $type->products->pluck('id')->all();
        $products = ManualSelectionOrder::sortModelsByIds(
            Product::orderBy('title')->limit(200)->get(),
            $selectedProductIds
        );
        return view('backend.layouts.cmsSettings.feature_types.edit', compact('type', 'products'));
    }

    public function update(UpdateFeatureTypeRequest $request, $id)
    {
        $type = FeatureType::findOrFail($id);

        $type->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'status' => $request->boolean('status', $type->status),
        ]);

        $type->products()->sync(ManualSelectionOrder::syncPayload($request->product_ids ?? []));

        return redirect()->route('cmsSettings.featureTypes.index')->with('success', 'Feature type updated');
    }

    public function destroy($id)
    {
        $type = FeatureType::findOrFail($id);
        $type->products()->detach();
        $type->delete();
        return redirect()->route('cmsSettings.featureTypes.index')->with('success', 'Feature type deleted');
    }
}
