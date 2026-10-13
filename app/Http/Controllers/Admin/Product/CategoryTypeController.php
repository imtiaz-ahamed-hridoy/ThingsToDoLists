<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CategoriesRepositoryInterface;
use App\Repositories\Contracts\CategoryTypeRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryTypeController extends Controller
{
    public function __construct(
        private CategoryTypeRepositoryInterface $categoryTypeRepository,
        private CategoriesRepositoryInterface $categoriesRepository
    ) {}

    public function index()
    {
        $types = $this->categoryTypeRepository->query()
            ->withCount('categories')
            ->orderByDesc('status')
            ->orderBy('name')
            ->get();

        return view('backend.layouts.category-types.index', compact('types'));
    }

    public function create()
    {
        return view('backend.layouts.category-types.create');
    }

    public function store(Request $request)
    {
        $slug = Str::slug((string) $request->input('slug', $request->input('name')), '_');
        $request->merge(['slug' => $slug]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:category_types,slug',
            'status' => 'required|in:0,1',
        ]);

        $this->categoryTypeRepository->create($validated);

        return redirect()
            ->route('category-type.index')
            ->with('t-success', 'Category type created successfully.');
    }

    public function edit(string $id)
    {
        $type = $this->categoryTypeRepository->findOrFail((int) $id);

        return view('backend.layouts.category-types.edit', compact('type'));
    }

    public function update(Request $request, string $id)
    {
        $type = $this->categoryTypeRepository->findOrFail((int) $id);

        $slug = Str::slug((string) $request->input('slug', $request->input('name')), '_');
        $request->merge(['slug' => $slug]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:category_types,slug,' . $type->id,
            'status' => 'required|in:0,1',
        ]);

        $originalSlug = $type->slug;
        $this->categoryTypeRepository->update($type, $validated);

        if ($originalSlug !== $validated['slug']) {
            $this->categoriesRepository->query()
                ->where('type', $originalSlug)
                ->update(['type' => $validated['slug']]);
        }

        return redirect()
            ->route('category-type.index')
            ->with('t-success', 'Category type updated successfully.');
    }

    public function destroy(string $id)
    {
        $type = $this->categoryTypeRepository->findOrFail((int) $id);

        if ($type->slug === 'product') {
            return response()->json([
                'status' => 'error',
                'message' => 'The product type is required and cannot be deleted.',
            ], 400);
        }

        if ($type->categories()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This type is assigned to categories. Reassign those categories first.',
            ], 400);
        }

        $this->categoryTypeRepository->delete($type);

        return response()->json([
            'status' => 'success',
            'message' => 'Category type deleted successfully.',
        ]);
    }
}
