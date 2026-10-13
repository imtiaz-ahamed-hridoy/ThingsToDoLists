@extends('backend.app')

@section('title', 'Create Category')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between">
        <div class="mb-2 mb-sm-0">
            <h1 class="page-title">Create Category</h1>
        </div>
        <div class="ms-sm-auto pageheader-btn">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Create</a></li>
                <li class="breadcrumb-item active">Category</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}

    <div class="row">
        <div class="col-12">
            <div class="card box-shadow-0">
                <div class="card-body">

                    <form action="{{ route('category.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        {{-- Category Name --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name</label>
                            <input type="text" name="name" id="name" class="form-control"
                                value="{{ old('name') }}" required>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Slug --}}
                        <div class="mb-3">
                            <label for="slug" class="form-label">Category Slug</label>
                            <input type="text" name="slug" id="slug" class="form-control"
                                value="{{ old('slug') }}" required>
                            @error('slug')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Type (fixed to product) --}}
                        <input type="hidden" name="type" value="product">


                        {{-- Parent Category --}}
                        <div class="mb-3">
                            <label for="parent_category_id" class="form-label">Parent Category (Optional)</label>
                            <select name="parent_category_id" id="parent_category_id" class="form-control">
                                <option value="">-- Select Parent Category --</option>

                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                        {{ old('parent_category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach

                            </select>
                            @error('parent_category_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- City/Location --}}
                        <div class="mb-3">
                            <label for="city_id" class="form-label">Location / City (Optional)</label>
                            <select name="city_id" id="city_id" class="form-control select2">
                                <option value="">-- Global Collection (No Specific City) --</option>

                                @foreach ($cities as $city)
                                    <option value="{{ $city->id }}"
                                        data-country="{{ $city->country_name }}"
                                        {{ old('city_id') == $city->id ? 'selected' : '' }}>
                                        {{ $city->city_name }}
                                    </option>
                                @endforeach

                            </select>
                            <small class="text-muted">Select a city to explicitly bind this collection to a location (e.g. for "Paris River Cruises").</small>
                            @error('city_id')
                                <span class="text-danger d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Country/Location --}}
                        <div class="mb-3">
                            <label for="location_country" class="form-label">Location / Country (Optional)</label>
                            <select name="location_country" id="location_country" class="form-control select2">
                                <option value="">-- Global Collection (No Specific Country) --</option>

                                @foreach ($countries as $country)
                                    <option value="{{ $country->country_name }}"
                                        {{ old('location_country') == $country->country_name ? 'selected' : '' }}>
                                        {{ $country->country_name }}
                                    </option>
                                @endforeach

                            </select>
                            <small class="text-muted">Select a country to explicitly bind this collection to a country-level location (e.g. for "Italy Tours"). If a city is selected above, this will also be derived automatically.</small>
                            @error('location_country')
                                <span class="text-danger d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Status --}}
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-control" required>
                                <option value="1" {{ old('status') == 1 ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('status') == 0 ? 'selected' : '' }}>Deactivate</option>
                            </select>
                            @error('status')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Search Display --}}
                        <div class="border p-3 mb-3">
                            <h6 class="mb-3">Search Display</h6>
                            <input type="hidden" name="show_in_search" value="0">
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" name="show_in_search" id="show_in_search"
                                    class="form-check-input" value="1"
                                    {{ old('show_in_search', 1) ? 'checked' : '' }}>
                                <label for="show_in_search" class="form-check-label">
                                    Show this category as a search collection
                                </label>
                            </div>
                        </div>

                        {{-- Products / Activities --}}
                        <div class="mb-3">
                            <label for="product_ids" class="form-label">Products / Activities</label>
                            @php
                                $selectedProductIds = collect(old('product_ids', []))->map(fn($id) => (int) $id)->all();
                            @endphp

                            <div class="d-flex flex-column flex-sm-row flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 flex-sm-grow-0" id="select-all-products">
                                    Select All Products
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 flex-sm-grow-0" id="select-unassigned-products">
                                    Select Unassigned Products
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger flex-grow-1 flex-sm-grow-0" id="clear-products">
                                    Clear Selection
                                </button>
                            </div>
                            <div class="d-flex flex-column flex-md-row gap-2 mb-2">
                                <input type="text" class="form-control" id="bulk_product_search"
                                    placeholder="Type product title, provider, or current category to bulk select">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary flex-grow-1 flex-sm-grow-0 text-nowrap" id="add-matching-products">
                                        Add Matching
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary flex-grow-1 flex-sm-grow-0 text-nowrap" id="select-only-matching-products">
                                        Select Only Matching
                                    </button>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label for="product_api_ids" class="form-label">Bulk Product API IDs</label>
                                <textarea name="product_api_ids" id="product_api_ids" class="form-control" rows="2"
                                    placeholder="Example: 149638, 36609, supplier-show-1">{{ old('product_api_ids') }}</textarea>
                                <div class="d-flex flex-column flex-sm-row flex-wrap gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 flex-sm-grow-0" id="add-api-id-products">
                                        Add API ID Products
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 flex-sm-grow-0" id="select-only-api-id-products">
                                        Select Only API ID Products
                                    </button>
                                </div>
                                <small class="text-muted">Paste comma-separated product_api_id values. These are also resolved when the form is submitted.</small>
                                @error('product_api_ids')
                                    <span class="text-danger d-block">{{ $message }}</span>
                                @enderror
                            </div>
                            <small class="text-muted d-block mb-2" id="product_selection_count">
                                0 products selected.
                            </small>
                            <select name="product_ids[]" id="product_ids" class="form-control select2" multiple>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-category-id="{{ $product->category_id }}"
                                        data-product-api-id="{{ $product->product_api_id }}"
                                        data-search="{{ \Illuminate\Support\Str::lower($product->title . ' ' . ($product->product_api_id ?? '') . ' ' . ($product->provider ?? $product->platform ?? 'manual') . ' ' . ($product->category?->name ?? '')) }}"
                                        data-city="{{ \Illuminate\Support\Str::lower($product->location?->city ?? '') }}"
                                        data-country="{{ \Illuminate\Support\Str::lower($product->location?->country ?? '') }}"
                                        {{ in_array($product->id, $selectedProductIds, true) ? 'selected' : '' }}>
                                        {{ $product->title }}
                                        ({{ ucfirst($product->provider ?? $product->platform ?? 'manual') }})
                                        @if ($product->product_api_id)
                                            - API: {{ $product->product_api_id }}
                                        @endif
                                        @if ($product->category)
                                            - current: {{ $product->category->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Selected products will be assigned to this category.</small>
                            @error('product_ids')
                                <span class="text-danger d-block">{{ $message }}</span>
                            @enderror
                            @error('product_ids.*')
                                <span class="text-danger d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Submit Buttons --}}
                        <div class="form-group mt-3 d-grid d-sm-flex gap-2">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('category.index') }}" class="btn btn-danger m-0">Cancel</a>
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
            const productSelect = $('#product_ids');
            const locationCountrySelect = $('#location_country');
            const locationCitySelect = $('#city_id');
            let allProductOptions = [];

            // Store original options for filtering
            productSelect.find('option').each(function() {
                allProductOptions.push($(this).clone());
            });

            productSelect.select2({
                placeholder: 'Select products / activities',
                width: '100%',
                closeOnSelect: false
            });

            productSelect.on('select2:select', function(event) {
                const selectedOption = event.params.data.element;
                if (selectedOption) {
                    $(selectedOption).detach().appendTo(this);
                    $(this).trigger('change.select2');
                }
            });

            locationCountrySelect.select2({ width: '100%', allowClear: true, placeholder: '-- Global Collection (No Specific Country) --' });
            locationCitySelect.select2({ width: '100%', allowClear: true, placeholder: '-- Global Collection (No Specific City) --' });

            // ── Auto-populate location_country when city_id is chosen ──
            locationCitySelect.on('change', function() {
                const selectedCountry = $(this).find('option:selected').attr('data-country') || '';
                if (selectedCountry) {
                    locationCountrySelect.val(selectedCountry).trigger('change.select2');
                }
                filterProducts();
            });

            locationCountrySelect.on('change', function() {
                filterProducts();
            });

            function filterProducts() {
                const selectedCountry = (locationCountrySelect.val() || '').toLowerCase().trim();
                const selectedCityVal = locationCitySelect.val() || '';
                const selectedCity = selectedCityVal ? (locationCitySelect.find('option:selected').text() || '').toLowerCase().trim() : '';

                const currentSelectedValues = productSelect.val() || [];

                if (productSelect.hasClass("select2-hidden-accessible")) {
                    productSelect.select2('destroy');
                }
                productSelect.empty();

                allProductOptions.forEach(function(opt) {
                    const optCity = (opt.attr('data-city') || '').toLowerCase().trim();
                    const optCountry = (opt.attr('data-country') || '').toLowerCase().trim();

                    let matches = true;

                    if (selectedCity) {
                        if (optCity !== selectedCity && !optCity.includes(selectedCity)) matches = false;
                    } else if (selectedCountry) {
                        if (optCountry !== selectedCountry && !optCountry.includes(selectedCountry)) matches = false;
                    }

                    // Always keep currently selected options available in the dropdown
                    if (matches || currentSelectedValues.includes(opt.val())) {
                        productSelect.append(opt.clone());
                    }
                });

                currentSelectedValues.forEach(function(id) {
                    const option = productSelect.find('option[value="' + id + '"]');
                    if (option.length) {
                        option.detach().appendTo(productSelect);
                    }
                });

                productSelect.select2({
                    placeholder: 'Select products / activities',
                    width: '100%',
                    closeOnSelect: false
                });
                productSelect.val(currentSelectedValues).trigger('change.select2');
                updateSelectionCount();
            }

            function updateSelectionCount() {
                const selectedValues = productSelect.val() || [];
                $('#product_selection_count').text(selectedValues.length + ' products selected.');
            }

            function applyBulkSelection(newSelectedIds, replace = false) {
                let finalIds = newSelectedIds;
                if (!replace) {
                    const currentIds = productSelect.val() || [];
                    finalIds = [...new Set([...currentIds, ...newSelectedIds])];
                }

                // Append all options temporarily so select2 can accept values not currently visible
                if (productSelect.hasClass("select2-hidden-accessible")) {
                    productSelect.select2('destroy');
                }
                productSelect.empty();
                allProductOptions.forEach(function(opt) {
                    productSelect.append(opt.clone());
                });

                finalIds.forEach(function(id) {
                    const option = productSelect.find('option[value="' + id + '"]');
                    if (option.length) {
                        option.detach().appendTo(productSelect);
                    }
                });
                productSelect.val(finalIds);
                filterProducts(); // This will re-initialize select2 and hide non-matching/non-selected options
            }

            function setSelectedProducts(filterCallback = null) {
                const selectedIds = [];

                allProductOptions.forEach(function(option) {
                    if (!filterCallback || filterCallback(option)) {
                        selectedIds.push(option.val());
                    }
                });

                applyBulkSelection(selectedIds, true);
            }

            function matchingProductIds() {
                const search = $('#bulk_product_search').val().trim().toLowerCase();
                const selectedIds = [];

                if (!search) {
                    return null;
                }

                allProductOptions.forEach(function(option) {
                    const searchableText = option.attr('data-search') || option.text().toLowerCase();

                    if (searchableText.includes(search)) {
                        selectedIds.push(option.val());
                    }
                });

                return selectedIds;
            }

            function parseApiIds() {
                return $('#product_api_ids').val()
                    .split(/[\r\n,]+/)
                    .map(function(apiId) {
                        return apiId.trim();
                    })
                    .filter(Boolean);
            }

            function productIdsFromApiIds() {
                const apiIds = parseApiIds();
                const selectedIds = [];

                if (!apiIds.length) {
                    return null;
                }

                allProductOptions.forEach(function(option) {
                    const productApiId = option.attr('data-product-api-id');

                    if (apiIds.includes(productApiId)) {
                        selectedIds.push(option.val());
                    }
                });

                return selectedIds;
            }

            $('#select-all-products').on('click', function() {
                setSelectedProducts();
            });

            $('#select-unassigned-products').on('click', function() {
                setSelectedProducts(function(option) {
                    return !option.attr('data-category-id');
                });
            });

            $('#clear-products').on('click', function() {
                applyBulkSelection([], true);
            });

            $('#add-matching-products').on('click', function() {
                const matchingIds = matchingProductIds();
                if (matchingIds === null) {
                    return;
                }
                applyBulkSelection(matchingIds, false);
            });

            $('#select-only-matching-products').on('click', function() {
                const matchingIds = matchingProductIds();
                if (matchingIds === null) {
                    return;
                }
                applyBulkSelection(matchingIds, true);
            });

            $('#add-api-id-products').on('click', function() {
                const apiProductIds = productIdsFromApiIds();
                if (apiProductIds === null) {
                    return;
                }
                applyBulkSelection(apiProductIds, false);
            });

            $('#select-only-api-id-products').on('click', function() {
                const apiProductIds = productIdsFromApiIds();
                if (apiProductIds === null) {
                    return;
                }
                applyBulkSelection(apiProductIds, true);
            });

            productSelect.on('change', updateSelectionCount);
            updateSelectionCount();
        });
    </script>
@endpush
