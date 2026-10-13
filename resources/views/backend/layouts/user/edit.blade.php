@extends('backend.app')

@section('title', 'User Edit')

@section('content')
 <style>
        /* custom form style */
        .form-control {
            border-radius: 6px;
            color: #333;
            transition: 0.3s;
        }

        .form-control:focus {
            background-color: #ffffff;
        }
    </style>
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">User Form</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">User</a></li>
                <li class="breadcrumb-item active" aria-current="page">User</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}


    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form method="post" action="{{ route('user.update', ['id' => $data->id]) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')

                        <div class="form-group">
                            <label for="name" class="form-label">Name:</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                name="name" id="name" value="{{ old('name', $data->name) }}">
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email"
                                id="email" value="{{ old('email', $data->email) }}">
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">Phone:</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone"
                                id="phone" value="{{ old('phone', $data->phone ?? '') }}">
                            @error('phone')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="dob" class="form-label">Date Of Birth:</label>
                            <input type="date" class="form-control @error('dob') is-invalid @enderror" name="dob"
                                id="dob" value="{{ old('dob', $data->dob ?? '') }}">
                            @error('dob')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password:</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                name="password" id="password" placeholder="Leave blank to keep current password">
                            @error('password')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="avatar" class="form-label">Avatar:</label>
                            <input type="file" class="dropify @error('avatar') is-invalid @enderror" name="avatar"
                                id="avatar"
                                data-default-file="{{ $data->avatar ?? '' }}">
                            @error('avatar')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" class="form-control">
                                <option value="User" {{ old('role', $data->role) === 'User' ? 'selected' : '' }}>User
                                </option>
                                <option value="Company" {{ old('role', $data->role) === 'Company' ? 'selected' : '' }}>
                                    Company</option>
                                <option value="Admin" {{ old('role', $data->role) === 'Admin' ? 'selected' : '' }}>Admin
                                </option>
                                 <option value="Support"  {{ old('role', $data->role) === 'Support' ? 'selected' : '' }}>Customer Support</option>
                                 <option value="supplier" {{ old('role', $data->role) === 'supplier' ? 'selected' : '' }}>Supplier</option>
                                 <option value="ContentCreator" {{ old('role', $data->role) === 'ContentCreator' ? 'selected' : '' }}>Content Creator</option>
                            </select>

                            @error('role')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>



                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('user.index') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
