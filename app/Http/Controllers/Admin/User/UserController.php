<?php

namespace App\Http\Controllers\Admin\User;

use Illuminate\View\View;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Hash, Storage};
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Http\Controllers\Controller;
use Yajra\DataTables\DataTables;

class UserController extends Controller
{
    /**
     * Display a listing of all users.
     *
     * @param Request $request
     * @return JsonResponse|View
     */
    public function index(Request $request): JsonResponse | View
    {

        if ($request->ajax()) {
            $data = User::latest()->get();

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('avatar', function ($data) {
                    $defaultImage = asset('frontend/default-avatar-profile.jpg');
                    // $data->avatar accessor returns a full URL when present
                    $url = $data->avatar ?: $defaultImage;

                    return '<img src="' . $url . '" alt="Avatar" width="50" height="50" style="object-fit:cover; border-radius:50%;">';
                })
                ->addColumn('action', function ($data) {
                    return '<div class="btn-group btn-group-sm" role="group" aria-label="Basic example">
                                    <a href="' . route('user.edit', ['id' => $data->id]) . '" type="button" class="btn btn-primary fs-14 text-white edit-icn" title="Edit">
                                        <i class="fe fe-edit"></i>
                                    </a>
                                    <a href="#" type="button" onclick="showDeleteConfirm(' . $data->id . ')" class="btn btn-danger fs-14 text-white delete-icn" title="Delete">
                                        <i class="fe fe-trash"></i>
                                    </a>
                                </div>';
                })
                ->rawColumns(['avatar', 'action'])
                ->make(true);
        }
        return view('backend.layouts.user.index');
    }

    public function show($id)
    {
        $data = User::findOrFail($id);
        return view('backend.layouts.user.view', compact('data'));
    }


    public function create()
    {
        return view('backend.layouts.user.create');
    }

    public function store(StoreUserRequest $request)
    {

        DB::beginTransaction();

        try {
            // Handle avatar upload
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $avatarName = time() . '_avatar_' . uniqid() . '.' . $avatar->getClientOriginalExtension();
                $avatarPath = $avatar->storeAs('uploads/avatar', $avatarName, 'public');
            }

            // Create user
            $data = new User();
            $data->name = $request->name;
            $data->name = $request->name ?? NULL;
            $data->email = $request->email;
            $data->email_verified_at = now();
            $data->password = Hash::make($request->password);
            $data->dob = $request->dob ?? NULL;
            $data->phone = $request->phone ?? NULL;
            $data->role = $request->role;
            $data->save();

            if ($avatarPath) {
                User::where('id', $data->id)->update(['avatar' => $avatarPath]);
            }


            DB::commit();
            return redirect()->route('user.index')->with('t-success', 'User created successfully');
        } catch (\Exception $exception) {
            DB::rollBack(); // rollback on any error
            return redirect()->route('user.index')->with('t-error', 'User creation failed: ' . $exception->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $data = User::findOrFail($id);
        //dd($data);
        return view('backend.layouts.user.edit', compact('data'));
    }

    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        try {
            // Find the user
            $user = User::findOrFail($id);

            $avatarPath = $user->getRawOriginal('avatar');

            if ($request->hasFile('avatar')) {
                // Delete old avatar if exists
                if ($avatarPath && Storage::disk('public')->exists($avatarPath)) {
                    Storage::disk('public')->delete($avatarPath);
                }

                $avatar = $request->file('avatar');
                $avatarName = time() . '_avatar_' . uniqid() . '.' . $avatar->getClientOriginalExtension();
                $avatarPath = $avatar->storeAs('uploads/avatar', $avatarName, 'public');
            }

            $user->name = $request->name;
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            $user->dob = $request->dob ?? $user->dob;
            $user->role   = $request->role;
            $user->save();

            if ($request->hasFile('avatar')) {
                User::where('id', $user->id)->update(['avatar' => $avatarPath]);
            }


            return redirect()->route('user.index')->with('t-success', 'User info Updated Successfully.');
        } catch (\Exception $exception) {
            return redirect()->route('user.index')->with('t-error', 'User info failed to update');
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json([
            't-success' => true,
            'message'   => 'Deleted successfully.',
        ]);
    }
}


