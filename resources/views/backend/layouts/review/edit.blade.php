@extends('backend.app')

@section('title', 'Review Edit')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Review Form</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Table</a></li>
                <li class="breadcrumb-item active" aria-current="page">Review</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}


    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form method="post" action="{{ route('review.update', ['id' => $data->id]) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="name" class="form-label">User Name:</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                name="name" id="name"
                                value="{{ old('name', $data->users->first_name ?? '') }}" readonly>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="ratings" class="form-label">Ratings:</label>
                            <input type="ratings" class="form-control @error('ratings') is-invalid @enderror" name="ratings"
                                id="ratings" value="{{ old('ratings', $data->ratings) }}" readonly>
                            @error('ratings')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="comment" class="form-label">Comment:</label>
                            <input type="text" class="form-control @error('comment') is-invalid @enderror" name="comment"
                                id="comment" value="{{ old('comment', $data->comment ?? '') }}" readonly>
                            @error('comment')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="company" class="form-label">Company:</label>
                            <input type="text" class="form-control @error('company') is-invalid @enderror" name="company"
                                id="company" value="{{ old('company', $data->company ?? '') }}" readonly>
                            @error('company')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="Active" {{ old('status', $data->status) === 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Inactive" {{ old('status', $data->status) === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>

                            @error('status')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>



                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('review.index') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
