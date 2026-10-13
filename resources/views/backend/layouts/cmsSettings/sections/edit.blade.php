@extends('backend.app')

@section('title', 'Edit Section')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Section</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <a href="{{ route('cmsSettings.sections.index') }}" class="btn btn-primary btn-icon text-white me-2">
                <span><i class="fe fe-arrow-left"></i></span> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form method="post" action="{{ route('cmsSettings.sections.update', $section->id) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        {{-- Title --}}
                        <div class="row mb-4">
                            <label for="title" class="col-md-3 form-label">Title <span class="text-danger">*</span></label>
                            <div class="col-md-9">
                                <input class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                                    placeholder="e.g. Featured Destinations, Top Activities" type="text"
                                    value="{{ old('title', $section->title) }}">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Short Description --}}
                        <div class="row mb-4">
                            <label for="short_description" class="col-md-3 form-label">Short Description</label>
                            <div class="col-md-9">
                                <textarea class="form-control @error('short_description') is-invalid @enderror" id="short_description"
                                    name="short_description" placeholder="Brief description for this section" rows="3">{{ old('short_description', $section->short_description) }}</textarea>
                                @error('short_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Image --}}
                        <div class="row mb-4">
                            <label for="image" class="col-md-3 form-label">Image</label>
                            <div class="col-md-9">
                                <input class="form-control @error('image') is-invalid @enderror" id="image" name="image" type="file">
                                <small class="text-muted">Leave empty to keep existing image. Recommended: 1920x600px. Max 5MB.</small>
                                @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                @if($section->getRawOriginal('image'))
                                    <div class="mt-2">
                                        <label class="text-muted small">Current Image:</label>
                                        <div class="position-relative d-inline-block">
                                            <img src="{{ asset('storage/'.$section->getRawOriginal('image')) }}" alt="Current" width="200" class="border rounded">
                                        </div>
                                    </div>
                                @endif

                                <div class="mt-2" id="image-preview" style="display:none;">
                                    <label class="text-muted small">New Image Preview:</label>
                                    <img src="" alt="Preview" width="200" class="border rounded">
                                </div>
                            </div>
                        </div>

                        @if($section->section_type === 'locations')
                            {{-- ====== LOCATIONS SECTION ====== --}}
                            <div class="row mb-4">
                                <label class="col-md-3 form-label fw-semibold">
                                    Locations
                                    <small class="d-block text-muted fw-normal">Add cities or countries</small>
                                </label>
                                <div class="col-md-9">
                                    <div id="locations-container">
                                        @foreach($section->locations as $index => $loc)
                                            <div class="location-row card border mb-3">
                                                <div class="card-body p-3">
                                                    <button type="button" class="btn btn-sm btn-danger float-end remove-location">
                                                        <i class="fe fe-trash"></i>
                                                    </button>
                                                    <input type="hidden" name="locations[{{ $index }}][id]" value="{{ $loc->id }}">

                                                    <div class="row g-3">
                                                        {{-- Type --}}
                                                        <div class="col-12 col-md-4">
                                                            <label class="form-label">Type <span class="text-danger">*</span></label>
                                                            <select class="form-control location-type-select"
                                                                    name="locations[{{ $index }}][location_type]">
                                                                <option value="">Select Type</option>
                                                                <option value="country" {{ $loc->location_type == 'country' ? 'selected' : '' }}>Country</option>
                                                                <option value="city"    {{ $loc->location_type == 'city'    ? 'selected' : '' }}>City</option>
                                                            </select>
                                                        </div>

                                                        {{-- Location (pre-populated select, AJAX-driven by JS) --}}
                                                        <div class="col-12 col-md-8">
                                                            <label class="form-label">Location <span class="text-danger">*</span></label>
                                                            <select class="form-control location-id-select"
                                                                    name="locations[{{ $index }}][location_id]"
                                                                    data-loc-type="{{ $loc->location_type }}"
                                                                    data-selected-id="{{ $loc->location_id }}">
                                                                {{-- Pre-populate saved value so it shows correctly --}}
                                                                @if($loc->location_id)
                                                                    @if($loc->location_type === 'city')
                                                                        @php $cityObj = \App\Models\City::select('id','city_name','country_name')->find($loc->location_id); @endphp
                                                                        @if($cityObj)
                                                                            <option value="{{ $cityObj->id }}" selected>
                                                                                {{ $cityObj->city_name }}{{ $cityObj->country_name ? ' (' . $cityObj->country_name . ')' : '' }}
                                                                            </option>
                                                                        @endif
                                                                    @else
                                                                        <option value="{{ $loc->location_id }}" selected>{{ $loc->location_id }}</option>
                                                                    @endif
                                                                @endif
                                                            </select>
                                                        </div>

                                                        {{-- Image --}}
                                                        <div class="col-12">
                                                            <label class="form-label">Location Image</label>
                                                            <input type="file" class="form-control"
                                                                   name="locations[{{ $index }}][image]" accept="image/*">
                                                            @if($loc->getRawOriginal('image'))
                                                                <div class="mt-2">
                                                                    <img src="{{ asset('storage/' . $loc->getRawOriginal('image')) }}"
                                                                         width="80" class="border rounded" alt="Location image">
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>{{-- /row g-3 --}}
                                                </div>{{-- /card-body --}}
                                            </div>{{-- /location-row --}}
                                        @endforeach
                                    </div>{{-- /locations-container --}}

                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="add-location-btn">
                                        <i class="fe fe-plus me-1"></i> Add Location
                                    </button>
                                </div>
                            </div>

                        @else
                            {{-- ====== PRODUCTS SECTION ====== --}}
                            <div class="row mb-4">
                                <label for="product_ids" class="col-md-3 form-label">Select Products</label>
                                <div class="col-md-9">
                                    <small class="text-muted d-block mb-2" id="product_selection_count">0 products selected.</small>
                                    <select name="product_ids[]" id="product_ids" class="form-control select2" multiple>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}"
                                                {{ in_array($product->id, old('product_ids', $section->product_ids ?? [])) ? 'selected' : '' }}>
                                                {{ $product->title }}
                                                ({{ ucfirst($product->provider ?? $product->platform ?? 'manual') }})
                                                @if($product->product_api_id)
                                                    - API: {{ $product->product_api_id }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Select products to display in this section.</small>
                                    @error('product_ids')   <span class="text-danger d-block">{{ $message }}</span> @enderror
                                    @error('product_ids.*') <span class="text-danger d-block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        {{-- Status --}}
                        <div class="row mb-4">
                            <label for="status" class="col-md-3 form-label">Status</label>
                            <div class="col-md-9">
                                <select class="form-control @error('status') is-invalid @enderror" name="status">
                                    <option value="1" {{ old('status', $section->status) == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status', $section->status) == 0 ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Submit --}}
                        <div class="row justify-content-end">
                            <div class="col-sm-9">
                                <button class="btn btn-primary" type="submit">Update Section</button>
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
<style>
    .location-row .card-body { background: #f9f9fb; }
    .location-row { transition: box-shadow .15s; }
    .location-row:hover { box-shadow: 0 2px 10px rgba(0,0,0,.08); }
    
    /* Ensure Select2 fills the responsive columns */
    .select2-container { width: 100% !important; }
    .select2-container .select2-selection--single { height: 38px; border: 1px solid #e8ebf3; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }

    @media (max-width: 767px) {
        .location-row .float-end { float: none !important; margin-bottom: .5rem; display: block; width: 100%; text-align: right; }
    }
</style>
<script>
$(document).ready(function () {

    /* ──────────────────────────────────────────────────────────────────────
     *  AJAX URL for city / country search (powers Select2 dropdowns)
     * ────────────────────────────────────────────────────────────────────── */
    var SEARCH_URL = '{{ route("cmsSettings.sections.locationSearch") }}';

    /* ──────────────────────────────────────────────────────────────────────
     *  Helper: attach an AJAX-backed Select2 to a location dropdown
     *
     *  @param {HTMLElement} el            - the <select> element
     *  @param {string}      type          - 'city' | 'country'
     *  @param {string|null} preId         - previously saved value (id/name)
     *  @param {string|null} preText       - display text for the saved value
     * ────────────────────────────────────────────────────────────────────── */
    function initLocationSelect(el, type, preId, preText) {
        var $el = $(el);

        $el.select2({
            width        : '100%',
            placeholder  : type === 'city' ? 'Search city...' : 'Search country...',
            allowClear   : true,
            minimumInputLength: 0,
            ajax: {
                url        : SEARCH_URL,
                dataType   : 'json',
                delay      : 300,
                data       : function (params) {
                    return { type: type, q: params.term || '' };
                },
                processResults : function (data) {
                    return { results: data.results };
                },
                cache: true
            }
        });

        /* Restore a previously saved value without firing an extra AJAX call */
        if (preId) {
            var opt = new Option(preText || preId, preId, true, true);
            $el.append(opt).trigger('change');
        }
    }

    /* ──────────────────────────────────────────────────────────────────────
     *  Boot existing rows that were rendered by Blade (edit page load)
     * ────────────────────────────────────────────────────────────────────── */
    $('.location-row').each(function () {
        var $row  = $(this);
        var $type = $row.find('.location-type-select');
        var $loc  = $row.find('.location-id-select');
        var type  = $type.val();
        var selId = $loc.data('selected-id');

        /* Type dropdown — static options, no search */
        $type.select2({ width: '100%', minimumResultsForSearch: Infinity });

        if (type) {
            /* The option is already in the DOM (pre-populated by Blade),
               so we just attach Select2 AJAX without re-populating. */
            initLocationSelect($loc[0], type, null, null);
            /* Keep the Blade-rendered selected option visible */
            $loc.trigger('change');
        } else {
            $loc.select2({ width: '100%', placeholder: 'Select type first' }).prop('disabled', true);
        }
    });

    /* ──────────────────────────────────────────────────────────────────────
     *  When the admin picks a type — rebuild the location dropdown
     * ────────────────────────────────────────────────────────────────────── */
    $(document).on('change', '.location-type-select', function () {
        var $row  = $(this).closest('.location-row');
        var $loc  = $row.find('.location-id-select');
        var type  = $(this).val();

        /* Cleanly destroy any existing Select2 instance */
        if ($loc.hasClass('select2-hidden-accessible')) {
            $loc.select2('destroy');
        }
        $loc.val(null).empty().prop('disabled', false);

        if (type) {
            initLocationSelect($loc[0], type, null, null);
        } else {
            $loc.select2({ width: '100%', placeholder: 'Select type first' }).prop('disabled', true);
        }
    });

    /* ──────────────────────────────────────────────────────────────────────
     *  Remove a location row
     * ────────────────────────────────────────────────────────────────────── */
    $(document).on('click', '.remove-location', function () {
        $(this).closest('.location-row').remove();
    });

    /* ──────────────────────────────────────────────────────────────────────
     *  Add a new blank location row
     * ────────────────────────────────────────────────────────────────────── */
    var locationIndex = {{ $section->locations->count() }};

    $('#add-location-btn').on('click', function () {
        var idx = locationIndex;
        var html = [
            '<div class="location-row card border mb-3">',
            '  <div class="card-body p-3">',
            '    <button type="button" class="btn btn-sm btn-danger float-end remove-location">',
            '      <i class="fe fe-trash"></i>',
            '    </button>',
            '    <div class="row g-3">',
            '      <div class="col-12 col-md-4">',
            '        <label class="form-label">Type <span class="text-danger">*</span></label>',
            '        <select class="form-control location-type-select" name="locations[' + idx + '][location_type]">',
            '          <option value="">Select Type</option>',
            '          <option value="country">Country</option>',
            '          <option value="city">City</option>',
            '        </select>',
            '      </div>',
            '      <div class="col-12 col-md-8">',
            '        <label class="form-label">Location <span class="text-danger">*</span></label>',
            '        <select class="form-control location-id-select" name="locations[' + idx + '][location_id]"></select>',
            '      </div>',
            '      <div class="col-12">',
            '        <label class="form-label">Location Image</label>',
            '        <input type="file" class="form-control" name="locations[' + idx + '][image]" accept="image/*">',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('');

        var $row  = $(html);
        $('#locations-container').append($row);

        $row.find('.location-type-select').select2({ width: '100%', minimumResultsForSearch: Infinity });
        $row.find('.location-id-select').select2({ width: '100%', placeholder: 'Select type first' }).prop('disabled', true);

        locationIndex++;
    });

    /* ──────────────────────────────────────────────────────────────────────
     *  Products multi-select (non-location sections)
     * ────────────────────────────────────────────────────────────────────── */
    $('#product_ids').select2({
        placeholder   : 'Select products / activities',
        width         : '100%',
        closeOnSelect : false
    });

    $('#product_ids').on('select2:select', function (event) {
        var selectedOption = event.params.data.element;
        if (selectedOption) {
            $(selectedOption).detach().appendTo(this);
            $(this).trigger('change.select2');
        }
    });

    function updateSelectionCount() {
        var n = ($('#product_ids').val() || []).length;
        $('#product_selection_count').text(n + ' product' + (n !== 1 ? 's' : '') + ' selected.');
    }
    $('#product_ids').on('change', updateSelectionCount);
    updateSelectionCount();

    /* ──────────────────────────────────────────────────────────────────────
     *  Section banner image preview
     * ────────────────────────────────────────────────────────────────────── */
    $('#image').on('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
            $('#image-preview').find('img').attr('src', ev.target.result);
            $('#image-preview').show();
        };
        reader.readAsDataURL(file);
    });

});
</script>
@endpush
