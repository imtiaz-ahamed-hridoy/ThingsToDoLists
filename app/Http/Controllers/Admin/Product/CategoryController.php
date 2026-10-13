<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Categories;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Categories::with(['parentCategory', 'categoryType'])->withCount('products')->latest()->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('status', function ($row) {
                    return $row->status == 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })

                ->addColumn('type', function ($row) {
                    return e($row->categoryType?->name ?? Str::headline(str_replace('_', ' ', (string) ($row->type ?? 'N/A'))));
                })

                ->addColumn('parent_category_id', function ($row) {
                    return $row->parentCategory ? $row->parentCategory->name : '<span class="text-muted">N/A</span>';
                })

                ->addColumn('products_count', function ($row) {
                    return (int) $row->products_count;
                })

                ->addColumn('search_display', function ($row) {
                    $status = $row->show_in_search
                        ? '<span class="badge bg-success">Visible</span>'
                        : '<span class="badge bg-secondary">Hidden</span>';

                    $label = e($row->search_label ?: $row->name);
                    $sort = (int) ($row->search_sort_order ?? 0);

                    return $status . '<div class="small text-muted mt-1">' . $label . ' - order ' . $sort . '</div>';
                })

                ->addColumn('action', function ($row) {
                    $edit = '<a href="' . route('category.edit', $row->id) . '" class="btn btn-sm btn-primary me-1">Edit</a>';
                    $delete = '<button onclick="showDeleteConfirm(' . $row->id . ')" class="btn btn-sm btn-danger">Delete</button>';
                    return $edit . $delete;
                })

                ->rawColumns(['status', 'search_display', 'action', 'parent_category_id'])
                ->make(true);
        }

        return view('backend.layouts.categories.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Categories::where('parent_category_id', null)->get();
        $products = $this->productOptions();
        $cities = \App\Models\City::select('id', 'city_name', 'country_name', 'country_short_code')->orderBy('city_name')->get();
        $countries = \App\Models\City::whereNotNull('country_name')
                        ->whereNotNull('country_short_code')
                        ->select('country_name', 'country_short_code')
                        ->distinct()
                        ->orderBy('country_name')
                        ->get();

        return view('backend.layouts.categories.create', compact('categories', 'products', 'cities', 'countries'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);

        $validated = $request->validated();

        $productIds = $this->resolveProductIds($request);

        $countryCode = null;
        if ($validated['location_country'] ?? false) {
            $countryCity = \App\Models\City::where('country_name', $validated['location_country'])->first();
            $countryCode = $countryCity ? $countryCity->country_short_code : null;
        }

        try {
            DB::transaction(function () use ($validated, $productIds, $countryCode) {
                $category = Categories::create([
                    'name'                => $validated['name'],
                    'slug'                => $validated['slug'],
                    'type'                => $validated['type'],
                    'status'              => $validated['status'],
                    'parent_category_id'  => $validated['parent_category_id'] ?? null,
                    'city_id'             => $validated['city_id'] ?? null,
                    'location_country'    => $validated['location_country'] ?? null,
                    'location_country_code'=> $countryCode,
                    'show_in_search'      => (bool) ($validated['show_in_search'] ?? true),
                    'search_label'        => $validated['search_label'] ?? null,
                    'search_subtitle'     => $validated['search_subtitle'] ?? null,
                    'search_icon'         => $validated['search_icon'] ?? null,
                    'search_sort_order'   => (int) ($validated['search_sort_order'] ?? 0),
                ]);

                $this->syncCategoryProducts($category, $productIds);
            });

            return redirect()
                ->route('category.index')
                ->with('t-success', 'Category created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('t-error', 'Something went wrong while saving the category!');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $data = Categories::with('products:id,title,category_id')->findOrFail($id);
        $parentCategory = Categories::where('parent_category_id', null)->get();
        $products = $this->productOptions();
        $cities = \App\Models\City::select('id', 'city_name', 'country_name', 'country_short_code')->orderBy('city_name')->get();
        $countries = \App\Models\City::whereNotNull('country_name')
                        ->whereNotNull('country_short_code')
                        ->select('country_name', 'country_short_code')
                        ->distinct()
                        ->orderBy('country_name')
                        ->get();

        return view('backend.layouts.categories.edit', compact('data', 'parentCategory', 'products', 'cities', 'countries'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, string $id)
    {
        $category = Categories::findOrFail($id);

        $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);

        $validated = $request->validated();

        if (($validated['parent_category_id'] ?? null) == $category->id) {
            return redirect()->back()
                ->withInput()
                ->with('t-error', 'A category cannot be its own parent.');
        }

        $productIds = $this->resolveProductIds($request);

        $countryCode = null;
        if ($validated['location_country'] ?? false) {
            $countryCity = \App\Models\City::where('country_name', $validated['location_country'])->first();
            $countryCode = $countryCity ? $countryCity->country_short_code : null;
        }

        try {
            DB::transaction(function () use ($category, $validated, $productIds, $countryCode) {
                $category->update([
                    'name'                => $validated['name'],
                    'slug'                => $validated['slug'],
                    'type'                => $validated['type'],
                    'status'              => $validated['status'],
                    'parent_category_id'  => $validated['parent_category_id'] ?? null,
                    'city_id'             => $validated['city_id'] ?? null,
                    'location_country'    => $validated['location_country'] ?? null,
                    'location_country_code'=> $countryCode,
                    'show_in_search'      => (bool) ($validated['show_in_search'] ?? true),
                    'search_label'        => $validated['search_label'] ?? null,
                    'search_subtitle'     => $validated['search_subtitle'] ?? null,
                    'search_icon'         => $validated['search_icon'] ?? null,
                    'search_sort_order'   => (int) ($validated['search_sort_order'] ?? 0),
                ]);

                $this->syncCategoryProducts($category, $productIds);
            });

            return redirect()->route('category.index')
                ->with('t-success', 'Category updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('t-error', 'Something went wrong while updating the category!');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Categories::findOrFail($id);

        try {
            $childCount = Categories::where('parent_category_id', $category->id)->count();
            if ($childCount > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete category with child categories. Reassign or remove them first.',
                ], 400);
            }

            Product::where('category_id', $category->id)->update(['category_id' => null]);
            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong while deleting the category!',
            ], 500);
        }
    }

    private function productOptions()
    {
        return Product::with(['category', 'location'])
            ->select('id', 'title', 'provider', 'platform', 'product_api_id', 'category_id')
            ->orderBy('title')
            ->get();
    }



    private function resolveProductIds(Request $request): array
    {
        $selectedProductIds = collect($request->input('product_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id);

        $apiIds = $this->parseProductApiIds((string) $request->input('product_api_ids', ''));

        if ($apiIds === []) {
            return $selectedProductIds->unique()->values()->all();
        }

        $productsByApiId = Product::query()
            ->whereIn('product_api_id', $apiIds)
            ->get(['id', 'product_api_id'])
            ->groupBy(fn (Product $product) => (string) $product->product_api_id);

        $missingApiIds = collect($apiIds)
            ->reject(fn (string $apiId) => $productsByApiId->has($apiId))
            ->values()
            ->all();

        if ($missingApiIds !== []) {
            throw ValidationException::withMessages([
                'product_api_ids' => 'These product API IDs were not found: ' . implode(', ', $missingApiIds),
            ]);
        }

        $apiProductIds = $productsByApiId
            ->flatten(1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $selectedProductIds
            ->merge($apiProductIds)
            ->unique()
            ->values()
            ->all();
    }

    private function parseProductApiIds(string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', $value) ?: [])
            ->map(fn (string $apiId) => trim($apiId))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function syncCategoryProducts(Categories $category, array $productIds): void
    {
        $productIds = collect($productIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $assignedProducts = Product::where('category_id', $category->id);

        if ($productIds === []) {
            $assignedProducts->update([
                'category_id' => null,
                'category_position' => 0,
            ]);
            return;
        }

        $assignedProducts->whereNotIn('id', $productIds)->update([
            'category_id' => null,
            'category_position' => 0,
        ]);
        foreach ($productIds as $index => $productId) {
            Product::where('id', $productId)->update([
                'category_id' => $category->id,
                'category_position' => $index + 1,
            ]);
        }
    }
}
