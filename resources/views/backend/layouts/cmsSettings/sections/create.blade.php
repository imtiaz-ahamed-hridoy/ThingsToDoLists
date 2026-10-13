@extends('backend.app')

@section('title', 'Create Section')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Create Section</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <a href="{{ route('cmsSettings.sections.index') }}" class="btn btn-primary btn-icon text-white me-2">
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
                    <form method="post" action="{{ route('cmsSettings.sections.store') }}" enctype="multipart/form-data">
                        @csrf

                        {{-- Title --}}
                        <div class="row mb-4">
                            <label for="title" class="col-md-3 form-label">Title <span class="text-danger">*</span></label>
                            <div class="col-md-9">
                                <input class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                                    placeholder="e.g. Featured Destinations, Top Activities" type="text"
                                    value="{{ old('title') }}">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Short Description --}}
                        <div class="row mb-4">
                            <label for="short_description" class="col-md-3 form-label">Short Description</label>
                            <div class="col-md-9">
                                <textarea class="form-control @error('short_description') is-invalid @enderror" id="short_description"
                                    name="short_description" placeholder="Brief description for this section" rows="3">{{ old('short_description') }}</textarea>
                                @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Image --}}
                        <div class="row mb-4">
                            <label for="image" class="col-md-3 form-label">Image</label>
                            <div class="col-md-9">
                                <input class="form-control @error('image') is-invalid @enderror" id="image" name="image" type="file">
                                <small class="text-muted">Recommended size: 1920x600px. Max 5MB.</small>
                                @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="mt-2" id="image-preview" style="display:none;">
                                    <img src="" alt="Preview" width="200" class="border rounded">
                                </div>
                            </div>
                        </div>

                        {{-- Products / Activities --}}
                        <div class="row mb-4">
                            <label for="product_ids" class="col-md-3 form-label">Select Products</label>
                            <div class="col-md-9">
                                <small class="text-muted d-block mb-2" id="product_selection_count">
                                    0 products selected.
                                </small>
                                <select name="product_ids[]" id="product_ids" class="form-control select2" multiple>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            {{ in_array($product->id, old('product_ids', [])) ? 'selected' : '' }}>
                                            {{ $product->title }}
                                            ({{ ucfirst($product->provider ?? $product->platform ?? 'manual') }})
                                            @if($product->product_api_id)
                                                - API: {{ $product->product_api_id }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Select products to display in this section.</small>
                                @error('product_ids') <span class="text-danger d-block">{{ $message }}</span> @enderror
                                @error('product_ids.*') <span class="text-danger d-block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Status --}}
                        <div class="row mb-4">
                            <label for="status" class="col-md-3 form-label">Status</label>
                            <div class="col-md-9">
                                <select class="form-control @error('status') is-invalid @enderror" name="status">
                                    <option value="1" {{ old('status', 1) == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status') === 0 ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Submit --}}
                        <div class="row justify-content-end">
                            <div class="col-sm-9">
                                <button class="btn btn-primary" type="submit">Create Section</button>
                                <a href="{{ route('cmsSettings.sections.index') }}" class="btn btn-light ms-2">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#product_ids').select2({
                placeholder: 'Select products / activities',
                width: '100%',
                closeOnSelect: false
            });

            $('#product_ids').on('select2:select', function(event) {
                const selectedOption = event.params.data.element;
                if (selectedOption) {
                    $(selectedOption).detach().appendTo(this);
                    $(this).trigger('change.select2');
                }
            });

            // Update selection count
            function updateSelectionCount() {
                const selected = $('#product_ids').val() || [];
                $('#product_selection_count').text(selected.length + ' products selected.');
            }

            $('#product_ids').on('change', updateSelectionCount);
            updateSelectionCount();

            // Image preview
            $('#image').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = $('#image-preview');
                        preview.find('img').attr('src', e.target.result);
                        preview.show();
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
@endpush
