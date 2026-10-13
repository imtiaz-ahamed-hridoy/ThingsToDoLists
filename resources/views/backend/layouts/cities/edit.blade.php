@extends('backend.app')

@section('title', 'Edit City')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit City</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Edit</a></li>
                <li class="breadcrumb-item active" aria-current="page">City</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}

    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">

                    <form method="post" action="{{ route('cities.update', ['id' => $data->id]) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        {{-- City Name --}}
                        <div class="form-group">
                            <label for="city_name" class="form-label">City Name</label>
                            <input type="text" name="city_name" id="city_name" class="form-control"
                                value="{{ old('city_name', $data->city_name) }}" required>
                            @error('city_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- City Short Code --}}
                        <div class="form-group">
                            <label for="city_short_code" class="form-label">City Short Code</label>
                            <input type="text" name="city_short_code" id="city_short_code" class="form-control"
                                value="{{ old('city_short_code', $data->city_short_code) }}">
                            @error('city_short_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Is Capital --}}
                        <div class="form-group">
                            <label for="city_is_capital" class="form-label">Is Capital?</label>
                            <select name="city_is_capital" id="city_is_capital" class="form-control">
                                <option value="0"
                                    {{ old('city_is_capital', $data->city_is_capital) == 0 ? 'selected' : '' }}>No</option>
                                <option value="1"
                                    {{ old('city_is_capital', $data->city_is_capital) == 1 ? 'selected' : '' }}>Yes</option>
                            </select>
                            @error('city_is_capital')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Slug --}}
                        <div class="form-group">
                            <label for="slug" class="form-label">Slug</label>
                            <input type="text" name="slug" id="slug" class="form-control"
                                value="{{ old('slug', $data->slug) }}" required>
                            @error('slug')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Country Name --}}
                        <div class="form-group">
                            <label for="country_name" class="form-label">Country Name</label>
                            <input type="text" name="country_name" id="country_name" class="form-control"
                                value="{{ old('country_name', $data->country_name) }}">
                            @error('country_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Country Short Code --}}
                        <div class="form-group">
                            <label for="country_short_code" class="form-label">Country Short Code</label>
                            <input type="text" name="country_short_code" id="country_short_code" class="form-control"
                                value="{{ old('country_short_code', $data->country_short_code) }}">
                            @error('country_short_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Country Slug --}}
                        <div class="form-group">
                            <label for="country_slug" class="form-label">Country Slug</label>
                            <input type="text" name="country_slug" id="country_slug" class="form-control"
                                value="{{ old('country_slug', $data->country_slug) }}">
                            @error('country_slug')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Currency Code --}}
                        <div class="form-group">
                            <label for="currency_code" class="form-label">Currency Code</label>
                            <input type="text" name="currency_code" id="currency_code" class="form-control"
                                value="{{ old('currency_code', $data->currency_code) }}">
                            @error('currency_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Mobile Prefix --}}
                        <div class="form-group">
                            <label for="mobile_prefix" class="form-label">Mobile Prefix</label>
                            <input type="text" name="mobile_prefix" id="mobile_prefix" class="form-control"
                                value="{{ old('mobile_prefix', $data->mobile_prefix) }}">
                            @error('mobile_prefix')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Title --}}
                        <div class="form-group">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" name="title" id="title" class="form-control"
                                value="{{ old('title', $data->title) }}">
                            @error('title')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Short Description --}}
                        <div class="form-group">
                            <label for="short_description" class="form-label">Short Description</label>
                            <textarea name="short_description" id="short_description" class="form-control" rows="3">{{ old('short_description', $data->short_description) }}</textarea>
                            @error('short_description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Thumbnail Image --}} <div class="form-group"> <label for="thumbnail_image"
                                class="form-label">Thumbnail Image</label> <input type="file"
                                class="dropify @error('thumbnail_image') is-invalid @enderror" name="thumbnail_image"
                                id="thumbnail_image"
                                data-default-file="{{ $data->thumbnail_image ? asset('storage/' . $data->thumbnail_image) : '' }}">
                            @error('thumbnail_image')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Gallery Image --}}
                        <div class="form-group">
                            <label for="gallery_image" class="form-label">Gallery Images:</label>
                            <input type="file" class="dropify @error('gallery_image.*') is-invalid @enderror"
                                name="gallery_image[]" id="gallery_image" multiple
                                data-allowed-file-extensions="jpg jpeg png gif webp"
                                @if (isset($data) && $data->gallery_image) data-default-file="{{ asset('storage/' . json_decode($data->gallery_image)[0]) }}" @endif>
                            @error('gallery_image.*')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Status --}}
                        <div class="form-group">
                            <label for="item_status" class="form-label">Status</label>
                            <select name="item_status" id="item_status" class="form-control" required>
                                <option value="0"
                                    {{ old('item_status', $data->item_status) == '0' ? 'selected' : '' }}>Pending</option>
                                <option value="1"
                                    {{ old('item_status', $data->item_status) == '1' ? 'selected' : '' }}>Active</option>
                                <option value="2"
                                    {{ old('item_status', $data->item_status) == '2' ? 'selected' : '' }}>Deactivate
                                </option>
                                <option value="3"
                                    {{ old('item_status', $data->item_status) == '3' ? 'selected' : '' }}>Rejected</option>
                            </select>
                            @error('item_status')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Submit Actions --}}
                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Update</button>
                            <a href="{{ route('cities.index') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
