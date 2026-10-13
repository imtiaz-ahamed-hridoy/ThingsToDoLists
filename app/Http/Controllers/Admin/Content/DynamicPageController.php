<?php

namespace App\Http\Controllers\Admin\Content;

use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use App\Http\Requests\DynamicPage\StoreDynamicPageRequest;
use App\Http\Requests\DynamicPage\UpdateDynamicPageRequest;
use App\Repositories\Contracts\DynamicPageRepositoryInterface;

class DynamicPageController extends Controller
{
    public function __construct(
        private DynamicPageRepositoryInterface $dynamicPageRepository
    ) {}

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->dynamicPageRepository->query()
                ->latest()
                ->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('action', function ($data) {
                    return '<div class="btn-group btn-group-sm" role="group" aria-label="Basic example">
                                    <a href="' . route('page.edit', ['id' => $data->id]) . '" type="button" class="btn btn-primary fs-14 text-white edit-icn" title="Edit">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <a href="#" type="button" onclick="showDeleteConfirm(' . $data->id . ')" class="btn btn-danger fs-14 text-white delete-icn" title="Delete">
                                        <i class="fe fe-trash"></i>
                                    </a>
                                </div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return view('backend.layouts.dynamic.index');
    }


    public function create()
    {
        return view('backend.layouts.dynamic.create');
    }

    public function store(StoreDynamicPageRequest $request)
    {
        $validated = $request->validated();

        try {
            $this->dynamicPageRepository->create($validated);
            return redirect()->route('page.index')->with('t-success', 'Page created successfully');
        } catch (\Exception $exception) {
            return redirect()->route('page.index')->with('t-error', 'Page creation failed: ' . $exception->getMessage());
        }
    }

    public function edit($id)
    {
        $data = $this->dynamicPageRepository->findOrFail((int) $id);
        return view('backend.layouts.dynamic.edit', compact('data'));
    }

    public function update(UpdateDynamicPageRequest $request, $id)
    {
        $validated = $request->validated();
        try {
            $page = $this->dynamicPageRepository->findOrFail((int) $id);
            $this->dynamicPageRepository->update($page, $validated);
            return redirect()->route('page.index')->with('t-success', 'Page Updated Successfully');
        } catch (\Exception $exception) {
            return redirect()->route('page.index')->with('t-error', 'Failed to Page Update');
        }
    }

    public function destroy($id)
    {
        $this->dynamicPageRepository->deleteById((int) $id);
        return redirect()->route('page.index')->with('t-succes', 'Page Deleted Successfully!');
    }
}

