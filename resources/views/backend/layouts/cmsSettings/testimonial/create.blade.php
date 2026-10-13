@extends('backend.app')

@section('title', 'Create Testimonial')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Create Testimonial</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <a href="{{ route('cmsSettings.testimonial.index') }}" class="btn btn-primary btn-icon text-white me-2">
                <span>
                    <i class="fe fe-arrow-left"></i>
                </span> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form method="post" action="{{ route('cmsSettings.testimonial.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-4">
                            <label for="name" class="col-md-3 form-label">Name <span class="text-danger">*</span></label>
                            <div class="col-md-9">
                                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" placeholder="Enter Name" type="text" value="{{ old('name') }}">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <label for="country" class="col-md-3 form-label">Country</label>
                            <div class="col-md-9">
                                <input class="form-control @error('country') is-invalid @enderror" id="country" name="country" placeholder="Enter Country" type="text" value="{{ old('country') }}">
                                @error('country') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <label for="country_icon" class="col-md-3 form-label">Country Icon</label>
                            <div class="col-md-9">
                                <input class="form-control @error('country_icon') is-invalid @enderror" id="country_icon" name="country_icon" type="file">
                                
                                <div class="mt-2 text-center text-muted fw-bold">OR</div>

                                <textarea class="form-control mt-2" name="country_icon_manual" placeholder="Paste SVG code or Icon Class here (e.g. <i class='fa fa-flag'></i>)"></textarea>
                                
                                @error('country_icon') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <label for="avater" class="col-md-3 form-label">Avatar</label>
                            <div class="col-md-9">
                                <input class="form-control @error('avater') is-invalid @enderror" id="avater" name="avater" type="file">
                                @error('avater') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <label for="image" class="col-md-3 form-label">Main Image</label>
                            <div class="col-md-9">
                                <input class="form-control @error('image') is-invalid @enderror" id="image" name="image" type="file">
                                @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <label for="description" class="col-md-3 form-label">Description</label>
                            <div class="col-md-9">
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" placeholder="Enter Description" rows="3">{{ old('description') }}</textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <label for="footer_description" class="col-md-3 form-label">Footer Description</label>
                            <div class="col-md-9">
                                <textarea class="form-control @error('footer_description') is-invalid @enderror" id="footer_description" name="footer_description" placeholder="Enter Footer Description" rows="2">{{ old('footer_description') }}</textarea>
                                @error('footer_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <label for="rating" class="col-md-3 form-label">Rating <span class="text-danger">*</span></label>
                            <div class="col-md-9">
                                <input class="form-control @error('rating') is-invalid @enderror" id="rating" name="rating" type="number" min="1" max="5" value="{{ old('rating', 5) }}">
                                @error('rating') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <label for="status" class="col-md-3 form-label">Status <span class="text-danger">*</span></label>
                            <div class="col-md-9">
                                <select class="form-control @error('status') is-invalid @enderror" name="status">
                                    <option value="1" {{ old('status') == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status') == 0 ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row justify-content-end">
                            <div class="col-sm-9">
                                <button class="btn btn-primary" type="submit">Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
