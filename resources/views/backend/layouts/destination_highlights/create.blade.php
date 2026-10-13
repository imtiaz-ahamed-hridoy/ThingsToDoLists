@extends('backend.app')
@section('title', 'Destination Highlights')
@push('styles')
    <link href="{{ asset('assets/backend/css/datatable.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 38px !important;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px;
        }
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
        }
    </style>
@endpush
@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Create Destination Highlight</h5>
                    <a href="{{ route('destination-highlights.index') }}" class="btn btn-secondary btn-sm">Back</a>
                </div>
                <div class="card-body">
                    <form action="{{ route('destination-highlights.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        {{-- Name --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">Highlight Name</label>
                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Destination Type --}}
                        <div class="mb-3">
                            <label for="destination_type" class="form-label">Destination Type</label>
                            <select name="destination_type" id="destination_type" class="form-control" required>
                                <option value="" disabled selected>-- Select Type --</option>
                                <option value="city" {{ old('destination_type') == 'city' ? 'selected' : '' }}>City</option>
                                <option value="country" {{ old('destination_type') == 'country' ? 'selected' : '' }}>Country</option>
                            </select>
                            @error('destination_type')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Destination Selection --}}
                        <div class="mb-3" id="destination_wrapper" style="display: none;">
                            <label for="destination_id" class="form-label">Destination</label>
                            <select name="destination_id" id="destination_id" class="form-control select2" style="width: 100%;">
                                <option value="">-- Select Destination --</option>
                            </select>
                            @error('destination_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Products / Activities --}}
                        <div class="mb-3">
                            <label class="form-label">Products / Activities</label>
                            @php
                                $selectedProductIds = collect(old('product_ids', []))->map(fn($id) => (int) $id)->all();
                            @endphp
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-products">
                                    Select All Products
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-products">
                                    Clear Selection
                                </button>
                            </div>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" id="bulk_product_search"
                                    placeholder="Type product title, provider, or ID to filter">
                                <button type="button" class="btn btn-outline-primary" id="add-matching-products">
                                    Add Matching
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="select-only-matching-products">
                                    Select Only Matching
                                </button>
                            </div>
                            <div class="mb-2">
                                <label for="product_api_ids" class="form-label">Bulk Product API IDs</label>
                                <textarea name="product_api_ids" id="product_api_ids" class="form-control" rows="2"
                                    placeholder="Example: 149638, 36609, supplier-show-1">{{ old('product_api_ids') }}</textarea>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-api-id-products">
                                        Add API ID Products
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="select-only-api-id-products">
                                        Select Only API ID Products
                                    </button>
                                </div>
                                <small class="text-muted">Paste comma-separated product_api_id values. These are resolved when the form is submitted.</small>
                                @error('product_api_ids')
                                    <span class="text-danger d-block">{{ $message }}</span>
                                @enderror
                            </div>
                            <small class="text-muted d-block mb-2" id="product_selection_count">
                                0 products selected.
                            </small>
                            <select name="product_ids[]" id="product_ids" class="form-control select2" multiple="multiple" style="width: 100%;">
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-category-id="{{ $product->category_id }}"
                                        data-product-api-id="{{ $product->product_api_id }}"
                                        data-search="{{ \Illuminate\Support\Str::lower($product->title . ' ' . ($product->product_api_id ?? '') . ' ' . ($product->provider ?? $product->platform ?? 'manual') . ' ' . ($product->category?->name ?? '')) }}"
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
                            <small class="text-muted">Selected products will be assigned to this highlight.</small>
                            @error('product_ids')
                                <span class="text-danger d-block">{{ $message }}</span>
                            @enderror
                            @error('product_ids.*')
                                <span class="text-danger d-block">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Image --}}
                        <div class="mb-3">
                            <label for="image" class="form-label">Highlight Image</label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*">
                            @error('image')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="4">{{ old('description') }}</textarea>
                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Status --}}
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Save Highlight</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            const productSelect = $('#product_ids');
            
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

            $('#destination_type, #destination_id').select2();

            const citiesData = @json($cities);
            const countriesData = @json($countries);

            function updateSelectionCount() {
                const selectedValues = productSelect.val() || [];
                $('#product_selection_count').text(selectedValues.length + ' products selected.');
            }

            function applyProductSelection(selectedIds) {
                selectedIds.forEach(function(id) {
                    const option = productSelect.find('option[value="' + id + '"]');
                    if (option.length) {
                        option.detach().appendTo(productSelect);
                    }
                });
                productSelect.val(selectedIds).trigger('change');
            }

            function setSelectedProducts(filterCallback = null) {
                const selectedIds = [];

                productSelect.find('option').each(function() {
                    const option = $(this);

                    if (!filterCallback || filterCallback(option)) {
                        selectedIds.push(option.val());
                    }
                });

                applyProductSelection(selectedIds);
            }

            function matchingProductIds() {
                const search = $('#bulk_product_search').val().trim().toLowerCase();
                const selectedIds = [];

                if (!search) {
                    return null;
                }

                productSelect.find('option').each(function() {
                    const option = $(this);
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

                productSelect.find('option').each(function() {
                    const option = $(this);
                    const productApiId = option.attr('data-product-api-id');

                    if (apiIds.includes(productApiId)) {
                        selectedIds.push(option.val());
                    }
                });

                return selectedIds;
            }

            function updateDestinationOptions() {
                const type = $('#destination_type').val();
                const $destSelect = $('#destination_id');
                
                $destSelect.empty().append('<option value="">-- Select Destination --</option>');
                
                if (type === 'city') {
                    citiesData.forEach(city => {
                        $destSelect.append(new Option(`${city.city_name} (${city.country_name})`, city.id));
                    });
                    $('#destination_wrapper').show();
                } else if (type === 'country') {
                    countriesData.forEach(country => {
                        $destSelect.append(new Option(country.country_name, country.id));
                    });
                    $('#destination_wrapper').show();
                } else {
                    $('#destination_wrapper').hide();
                }

                $destSelect.val('').trigger('change');
            }

            $('#select-all-products').on('click', function() {
                setSelectedProducts();
            });

            $('#clear-products').on('click', function() {
                applyProductSelection([]);
            });

            $('#add-matching-products').on('click', function() {
                const matchingIds = matchingProductIds();
                if (matchingIds === null) {
                    return;
                }

                const currentIds = productSelect.val() || [];
                const selectedIds = [...new Set([...currentIds, ...matchingIds])];
                applyProductSelection(selectedIds);
            });

            $('#select-only-matching-products').on('click', function() {
                const matchingIds = matchingProductIds();
                if (matchingIds === null) {
                    return;
                }

                applyProductSelection(matchingIds);
            });

            $('#add-api-id-products').on('click', function() {
                const apiProductIds = productIdsFromApiIds();
                if (apiProductIds === null) {
                    return;
                }

                const currentIds = productSelect.val() || [];
                const selectedIds = [...new Set([...currentIds, ...apiProductIds])];
                applyProductSelection(selectedIds);
            });

            $('#select-only-api-id-products').on('click', function() {
                const apiProductIds = productIdsFromApiIds();
                if (apiProductIds === null) {
                    return;
                }

                applyProductSelection(apiProductIds);
            });

            $('#destination_type').change(updateDestinationOptions);

            productSelect.on('change', updateSelectionCount);
            updateSelectionCount();

            // Trigger if old values exist
            if ($('#destination_type').val()) {
                const oldDest = "{{ old('destination_id') }}";
                const type = $('#destination_type').val();
                
                $('#destination_wrapper').show();
                const $destSelect = $('#destination_id');
                $destSelect.empty().append('<option value="">-- Select Destination --</option>');
                
                if (type === 'city') {
                    citiesData.forEach(city => {
                        $destSelect.append(new Option(`${city.city_name} (${city.country_name})`, city.id));
                    });
                } else if (type === 'country') {
                    countriesData.forEach(country => {
                        $destSelect.append(new Option(country.country_name, country.id));
                    });
                }

                if (oldDest) {
                    $destSelect.val(oldDest).trigger('change');
                }
            }
        });
    </script>
@endpush
