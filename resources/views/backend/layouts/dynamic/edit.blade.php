@extends('backend.app')

@section('title', 'Dynamic Page Edit')

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
            <h1 class="page-title">Dynamic Page Form</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Create</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dynamic Page</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}


    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form method="post" action="{{ route('page.update', ['id' => $data->id]) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')

                        <!-- Page Title -->
                        <div class="mb-3">
                            <label for="page_title" class="form-label">Page Title</label>
                            <input type="text" name="page_title" id="page_title" class="form-control"
                                value="{{ old('page_title', $data->page_title ?? '') }}" required>
                            @error('page_title')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Page Slug -->
                        <div class="mb-3">
                            <label for="page_slug" class="form-label">Page Slug</label>
                            <input type="text" name="page_slug" id="page_slug" class="form-control"
                                value="{{ old('page_slug', $data->page_slug ?? '') }}" required>
                                 @error('page_slug')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Page Content -->
                        <div class="mb-3">
                            <label for="page_content" class="form-label">Page Content</label>
                            <textarea name="page_content" id="summernote" rows="6" class="form-control" required>{{ old('page_content', $data->page_content ?? '') }}</textarea>
                             @error('page_content')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="Active" {{ old('status', $data->status ?? '') == 1 ? 'selected' : '' }}>
                                    Active</option>
                                <option value="Inactive" {{ old('status', $data->status ?? '') == 0 ? 'selected' : '' }}>
                                    Inactive</option>
                            </select>
                             @error('status')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>


                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('page.index') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
