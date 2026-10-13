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
                    <form method="post" action="{{ route('faq.update', ['id' => $data->id]) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="question" class="form-label">Question:</label>
                            <input type="text" class="form-control @error('question') is-invalid @enderror"
                                name="question" id="question" value="{{ old('question', $data->question) }}">
                            @error('question')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="answer" class="form-label">Answer:</label>
                            <input type="text" class="form-control @error('answer') is-invalid @enderror"
                                name="answer" id="answer" value="{{ old('answer', $data->answer) }}">
                            @error('answer')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="Active" {{ old('status', $data->status) === 'Active' ? 'selected' : '' }}>Active
                                </option>
                                <option value="Inactive" {{ old('status', $data->status) === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>

                            @error('status')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>



                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('faq.index') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
