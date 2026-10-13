<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = NewsletterSubscriber::select(['id', 'email', 'name', 'number', 'status', 'created_at']);

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('status', function ($data) {
                    return $data->status === 'subscribed'
                        ? "<span class='badge bg-success'>Subscribed</span>"
                        : "<span class='badge bg-danger'>Unsubscribed</span>";
                })
                ->addColumn('subscribed_at', function ($data) {
                    return $data->created_at ? $data->created_at->format('d-m-Y H:i') : 'N/A';
                })
                ->addColumn('action', function ($data) {
                    $buttons = '<div class="btn-group btn-group-sm" role="group">';
                    $buttons .= '<a href="#" type="button" onclick="showDeleteConfirm(' . $data->id . ')" class="btn btn-danger fs-14 text-white delete-icn" title="Delete">
                        <i class="fe fe-trash"></i>
                    </a>';
                    $buttons .= '</div>';
                    return $buttons;
                })
                ->rawColumns(['action', 'status'])
                ->make();
        }

        return view('backend.layouts.newsletter.index');
    }

    public function destroy($id)
    {
        $subscriber = NewsletterSubscriber::findOrFail((int) $id);
        $subscriber->delete();

        return response()->json([
            't-success' => true,
            'message' => 'Subscriber deleted successfully',
        ], 200);
    }
}
