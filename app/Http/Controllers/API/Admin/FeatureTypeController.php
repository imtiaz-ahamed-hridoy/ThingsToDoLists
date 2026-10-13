<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureType;
use App\Support\ManualSelectionOrder;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class FeatureTypeController extends Controller
{
    use ApiResponse;

    private function isAdmin(Request $request): bool
    {
        $role = strtolower((string) ($request->user()?->role ?? ''));
        return in_array($role, ['admin', 'administrator', 'super-admin', 'superadmin'], true);
    }

    public function index(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $perPage = (int) $request->input('per_page', 15);
        $query = FeatureType::orderBy('sort_order')->orderBy('id');
        $paginator = $query->paginate(max(1, min($perPage, 200)));

        return $this->success('Feature types retrieved successfully.', $paginator);
    }

    public function store(Request $request)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:feature_types,slug',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $slug = $request->input('slug') ?? \Illuminate\Support\Str::slug($request->input('name'));

        $type = FeatureType::create([
            'name' => $request->input('name'),
            'slug' => $slug,
            'description' => $request->input('description'),
            'status' => $request->boolean('status', true),
            'sort_order' => $request->input('sort_order', (FeatureType::max('sort_order') ?? 0) + 1),
        ]);

        return $this->success('Feature type created successfully.', $type);
    }

    public function show(Request $request, $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $type = FeatureType::find($id);
        if (! $type) {
            return $this->error('Feature type not found', 404);
        }

        return $this->success('Feature type retrieved successfully.', $type);
    }

    public function update(Request $request, $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $type = FeatureType::find($id);
        if (! $type) {
            return $this->error('Feature type not found', 404);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:feature_types,slug,' . $type->id,
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $type->name = $request->input('name', $type->name);
        $type->slug = $request->input('slug', $type->slug);
        $type->description = $request->input('description', $type->description);
        $type->status = $request->boolean('status', $type->status);
        $type->sort_order = $request->input('sort_order', $type->sort_order);
        $type->save();

        return $this->success('Feature type updated successfully.', $type);
    }

    public function destroy(Request $request, $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $type = FeatureType::find($id);
        if (! $type) {
            return $this->error('Feature type not found', 404);
        }

        $type->products()->detach();
        $type->delete();

        return $this->success('Feature type deleted successfully.', []);
    }

    /**
     * Sync products for a feature type (admin only)
     */
    public function syncProducts(Request $request, $id)
    {
        if (! $this->isAdmin($request)) {
            return $this->error('Unauthorized access.', 403);
        }

        $type = FeatureType::find($id);
        if (! $type) {
            return $this->error('Feature type not found', 404);
        }

        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $type->products()->sync(ManualSelectionOrder::syncPayload($request->input('product_ids', [])));

        return $this->success('Feature products synced successfully.', $type->products()->pluck('id'));
    }
}
