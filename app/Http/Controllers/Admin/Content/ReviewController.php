<?php

namespace App\Http\Controllers\Admin\Content;

use App\Http\Requests\Review\UpdateReviewRequest;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;

class ReviewController extends Controller
{
    public function __construct(
        private ReviewRepositoryInterface $reviewRepository
    ) {}

    public function index(Request $request)
    {
        if ($request->ajax()) {
           $data = $this->reviewRepository->query()->with('users')->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('users', function ($data) {
                if ($data->users) {
                        return $data->users->name . ' (' . $data->users->email . ')';
                    }
                    return 'N/A';
                })
                ->addColumn('rating', function ($data) {
                    $rating = (int) $data->ratings; // convert to integer
                    $stars = '';
                    for ($i = 1; $i <= 5; $i++) {
                        $stars .= $i <= $rating
                            ? '<i class="fa fa-star text-success"></i>'   // filled star
                            : '<i class="fa fa-star-o text-muted"></i>'; // empty star
                    }
                    return $stars;
                })

                ->addColumn("status", function ($data) {
                    if ($data->status == 'Active') {
                        return "<span class='badge bg-success'>Active</span>";
                    }else {
                        return "<span class='badge bg-danger'>Inactive</span>";
                    }
                })
                ->addColumn('action', function ($data) {
                    $buttons = '<div class="btn-group btn-group-sm" role="group" aria-label="Basic example">';
                    // Edit button
                    $buttons .= '<a href="'.route('review.edit', ['id' => $data->id]).'" type="button" class="btn btn-primary fs-14 text-white edit-icn" title="Edit">
                    <i class="fe fe-edit"></i>
                    </a>';
                     $buttons .= '<a href="#" type="button" onclick="showDeleteConfirm(' . $data->id . ')" class="btn btn-danger fs-14 text-white delete-icn" title="Delete">
                    <i class="fe fe-trash"></i>
                    </a>';
                    $buttons .= '</div>';

                    return $buttons;
                })
                ->rawColumns(['action', 'users', 'status', 'rating'])
                ->make();
        }
        return view('backend.layouts.review.index');
    }

    public function edit($id)
    {
        $data = $this->reviewRepository->query()->with('users')->findOrFail((int) $id);
        return view('backend.layouts.review.edit', compact('data'));
    }

    public function update(UpdateReviewRequest $request)
    {
        $validated = $request->validated();
        $review = $this->reviewRepository->findOrFail((int) $request->id);
        $this->reviewRepository->update($review, ['status' => $validated['status']]);

        return redirect()->route('review.index')->with('t-success', 'Review status updated successfully!');
    }
    public function destroy($id)
    {
        $review = $this->reviewRepository->findOrFail((int) $id);
        if ($review) {
            $this->reviewRepository->delete($review);
        }
        return response()->json([
            't-success' => true,
            'message' => 'Data deleted successfully'
        ], 200);
    }
}

