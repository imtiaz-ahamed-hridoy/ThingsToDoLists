<?php

namespace App\Http\Controllers\Admin\System;

use App\Enums\ModelEnum;
use App\Repositories\Contracts\ActivityRepositoryInterface;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;

class ActivityController extends Controller
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository
    ) {}

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->activityRepository->getLatestWithCauser();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('causer', function ($data) {
                    return $data->causer->name ?? 'System';
                })
                ->addColumn('action', function ($data) {
                    $buttons = '<div class="btn-group btn-group-sm" role="group">';
                    $buttons .= '<button type="button" onclick="showDeleteConfirm(' . $data->id . ')" class="btn btn-danger text-white" title="Delete"><i class="fe fe-trash"></i></button>';
                    $buttons .= '</div>';
                    return $buttons;
                })
                ->addColumn('subject_type', function ($data) {
                    $subjectType = $data->subject_type;

                    $enum = ModelEnum::tryFrom($subjectType);

                    return $enum ? $enum->label() : class_basename($subjectType);
                })
                ->addColumn('attributes', function ($data) {
                    $html = '';

                    $properties = is_string($data->properties) ? json_decode($data->properties, true) : $data->properties;

                    if (isset($properties['attributes'])) {
                        foreach ($properties['attributes'] as $key => $value) {
                            $html .= ucfirst($key) . ': ' . $value . '<br>';
                        }
                    }

                    return $html;
                })
                ->rawColumns(['action', 'attributes'])
                ->make(true);
        }

        return view('backend.layouts.activity.index');
    }

    public function destroy($id)
    {
        $this->activityRepository->deleteById((int) $id);
        return response()->json(['t-success' => 'Deleted Successfully', 'message' => 'Deleted Successfully']);
    }
}

