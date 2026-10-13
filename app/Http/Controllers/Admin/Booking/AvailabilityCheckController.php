<?php

namespace App\Http\Controllers\Admin\Booking;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\{AvailabilityCheck, Product};
use Yajra\DataTables\DataTables;

class AvailabilityCheckController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = AvailabilityCheck::with('product')->latest();

            return DataTables::of($data)
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" class="row-checkbox" value="' . $row->id . '">';
                })
                ->addIndexColumn()
                ->addColumn('product', fn($row) => optional($row->product)->title ?? '<span class="text-muted">N/A</span>')
                ->addColumn('capacity', function ($row) {
                    return $row->unlimited_availability
                        ? '<span class="badge bg-info">Unlimited</span>'
                        : $row->capacity_booked . ' / ' . $row->capacity_total;
                })
                ->addColumn('status', function ($row) {
                    if (!$row->is_active) return '<span class="badge bg-secondary">Inactive</span>';
                    if ($row->sold_out) return '<span class="badge bg-danger">Sold Out</span>';
                    if ($row->is_closed) return '<span class="badge bg-warning">Closed</span>';
                    return '<span class="badge bg-success">Open</span>';
                })
                ->addColumn('action', function ($row) {
                    return '
                <div class="btn-group btn-group-sm" role="group">
                    <a href="' . route('availability.edit', $row->id) . '" class="btn btn-primary text-white" title="Edit"><i class="fe fe-edit"></i></a>
                    <a href="javascript:void(0);" onclick="showDeleteConfirm(' . $row->id . ')" class="btn btn-danger text-white" title="Delete"><i class="fe fe-trash"></i></a>
                </div>';
                })
                ->rawColumns(['checkbox', 'product', 'capacity', 'status', 'action'])
                ->make(true);
        }

        return view('backend.layouts.availability.index');
    }

    /* ... rest of existing methods ... */

    public function create()
    {
        $products = Product::all();
        return view('backend.layouts.availability.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'provider' => 'nullable|string|max:50',
            'available_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'capacity_total' => 'required|integer|min:0',
            'capacity_booked' => 'nullable|integer|min:0',
            'capacity_reserved' => 'nullable|integer|min:0',
            'min_participants' => 'nullable|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
        ]);

        $validated['unlimited_availability'] = $request->has('unlimited_availability');
        $validated['sold_out'] = $request->has('sold_out');
        $validated['is_closed'] = $request->has('is_closed');
        $validated['is_active'] = $request->has('is_active');

        try {
            AvailabilityCheck::create($validated);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->withInput()->withErrors(['duplicate' => 'This availability slot already exists.']);
            }
            throw $e;
        }

        return redirect()->route('availability.index')->with('success', 'Availability slot created successfully!');
    }

    public function edit($id)
    {
        // Fetch availability with related product to avoid N+1 if needed in blade
        $availability = AvailabilityCheck::with('product')->findOrFail($id);

        // Fetch all products for dropdown
        $products = Product::all();

        // Load the edit blade
        return view('backend.layouts.availability.edit', compact('availability', 'products'));
    }


    public function update(Request $request, $id)
    {
        $slot = AvailabilityCheck::findOrFail($id);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'provider' => 'nullable|string|max:50',
            'available_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'nullable',
            'capacity_total' => 'required|integer|min:0',
            'capacity_booked' => 'nullable|integer|min:0',
            'capacity_reserved' => 'nullable|integer|min:0',
            'min_participants' => 'nullable|integer|min:1',
            'max_participants' => 'nullable|integer|min:1',
        ]);

        $validated['unlimited_availability'] = $request->has('unlimited_availability');
        $validated['sold_out'] = $request->has('sold_out');
        $validated['is_closed'] = $request->has('is_closed');
        $validated['is_active'] = $request->has('is_active');

        try {
            $slot->update($validated);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->withInput()->withErrors(['duplicate' => 'This availability slot already exists.']);
            }
            throw $e;
        }

        return redirect()->route('availability.index')->with('success', 'Availability slot updated successfully!');
    }


    public function destroy($id)
    {
        try {
            $availability = AvailabilityCheck::findOrFail($id);
            $availability->delete();

            return response()->json([
                't-success' => true,
                'message' => 'Availability slot deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Availability delete error: ' . $e->getMessage());
            return response()->json([
                't-success' => false,
                'message' => 'Unable to delete this record. Please try again.'
            ], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $ids = $request->ids;
            if (!is_array($ids) || empty($ids)) {
                return response()->json([
                    't-success' => false,
                    'message' => 'No slots selected.',
                ], 400);
            }

            AvailabilityCheck::whereIn('id', $ids)->delete();

            return response()->json([
                't-success' => true,
                'message' => count($ids) . ' record(s) deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Availability bulk delete error: ' . $e->getMessage());
            return response()->json([
                't-success' => false,
                'message' => 'Bulk delete failed. Please try again.'
            ], 500);
        }
    }
}

