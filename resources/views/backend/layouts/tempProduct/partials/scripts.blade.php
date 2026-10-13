<script>
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            }
        });

        // ==================== INIT DATA TABLE ====================
        let dTable = $('#datatable').DataTable({
            order: [],
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            processing: true,
            responsive: true,
            serverSide: true,
            language: {
                processing: `<div class="text-center">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>`
            },
            scroller: {
                loadingIndicator: false
            },
            pagingType: "full_numbers",
            dom: "<'row justify-content-between table-topbar'<'col-md-2 col-sm-4 px-0'l><'col-md-2 col-sm-4 px-0'f>>tipr",
            ajax: {
                url: "{{ route('tempProduct.index') }}",
                type: "GET",
            },
            columns: [
                { data: 'checkbox', orderable: false, searchable: false },
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'product_api_id', name: 'product_api_id', orderable: true, searchable: true },
                { 
                    data: 'title', 
                    name: 'title', 
                    orderable: true, 
                    searchable: true,
                    render: function(data, type, row) {
                        if (type === 'display' && data) {
                            if (typeof data === 'string' && data.includes('<span')) {
                                return data;
                            }
                            const full = String(data);
                            const short = full.length > 50 ? full.substring(0, 50) + '...' : full;
                            return '<span title="' + $('<div>').text(full).html() + '">' + $('<div>').text(short).html() + '</span>';
                        }
                        return data;
                    }
                },
                { data: 'price', name: 'price', orderable: true, searchable: true },
                { data: 'city', name: 'city', orderable: true, searchable: true },
                { data: 'provider', name: 'provider', orderable: true, searchable: true },
                { data: 'thumbnail', name: 'thumbnail', orderable: false, searchable: false },
                { data: 'import_result', name: 'import_result', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
        });

        // ==================== SELECT ALL ====================
        $('#selectAll').on('change', function() {
            $('.row-checkbox').prop('checked', this.checked);
        });

        $(document).on('change', '.row-checkbox', function() {
            $('#selectAll').prop(
                'checked',
                $('.row-checkbox').length === $('.row-checkbox:checked').length
            );
        });

        // ==================== OPEN BOKUN SEARCH MODAL ====================
        window.openBokunSearchModal = function() {
            $('#bokunSearchForm')[0].reset();
            $('#bokunSearchCriteriaSection').show();
            $('#bokunAdvancedSection').collapse('hide');
            $('#bokunPageNumber, #bokunPageSize').prop('required', true);
            $('#bokunSearchModal').modal('show');
        };

        // ==================== TOGGLE BOKUN SEARCH CRITERIA ====================
        $(document).on('input', '#bokunProductApiId', function() {
            if ($(this).val().trim() !== '') {
                $('#bokunSearchCriteriaSection').slideUp();
                $('#bokunPageNumber, #bokunPageSize').prop('required', false);
            } else {
                $('#bokunSearchCriteriaSection').slideDown();
                $('#bokunPageNumber, #bokunPageSize').prop('required', true);
            }
        });

        // ==================== OPEN GLOBALTIX SEARCH MODAL ====================
        window.openGlobalTixSearchModal = function() {
            $('#globaltixSearchForm')[0].reset();
            $('#gtSearchCriteriaSection').show();
            $('#gtCountryCode, #gtPageNumber, #gtPageSize').prop('required', true);
            loadGlobalTixCountries();
            loadGlobalTixCategories();
            $('#globaltixSearchModal').modal('show');
        };

        // ==================== TOGGLE GLOBALTIX SEARCH CRITERIA ====================
        $(document).on('input', '#gtProductApiId', function() {
            if ($(this).val().trim() !== '') {
                $('#gtSearchCriteriaSection').slideUp();
                $('#gtCountryCode, #gtPageNumber, #gtPageSize').prop('required', false);
            } else {
                $('#gtSearchCriteriaSection').slideDown();
                $('#gtCountryCode, #gtPageNumber, #gtPageSize').prop('required', true);
            }
        });

        // ==================== OPEN SEARCH MODAL (Legacy - delegates to new functions) ====================
        window.openSearchModal = function(platform) {
            if (platform === 'bokun') {
                openBokunSearchModal();
            } else {
                openGlobalTixSearchModal();
            }
        };

        // ==================== GLOBALTIX: Load Countries ====================
        let globalTixData = [];

        function loadGlobalTixCountries() {
            const countrySelect = $('#gtCountryCode');
            countrySelect.prop('disabled', true).html('<option>Loading...</option>');

            $.ajax({
                url: "{{ route('tempProduct.globaltix.countries') }}",
                type: "GET",
                success: function(response) {
                    let options = '<option value="" selected disabled>Select a country...</option>';
                    if(response.success && response.data) {
                        globalTixData = response.data;
                        response.data.forEach(function(country) {
                            options += `<option value="${country.code}">${country.name} (${country.code})</option>`;
                        });
                        toastr.success('Countries loaded successfully');
                    } else {
                        options += '<option value="" disabled>Failed to load countries</option>';
                        toastr.error('Failed to load countries from GlobalTix API');
                    }
                    countrySelect.html(options).prop('disabled', false);
                },
                error: function() {
                    countrySelect.html('<option value="">Failed to load</option>').prop('disabled', false);
                    toastr.error('Failed to load countries (API Error)');
                }
            });
        }

        // ==================== GLOBALTIX: Load Categories (from REAL API) ====================
        function loadGlobalTixCategories() {
            const categorySelect = $('#gtCategory');
            categorySelect.prop('disabled', true).html('<option>Loading categories...</option>');

            $.ajax({
                url: "{{ route('tempProduct.globaltix.categories') }}",
                type: "GET",
                success: function(response) {
                    let options = '<option value="">All Categories</option>';
                    if(response.success && response.data && Array.isArray(response.data)) {
                        response.data.forEach(function(cat) {
                            const catId = cat.id ?? cat.categoryId ?? cat.code ?? '';
                            const catName = cat.name ?? cat.title ?? cat.label ?? catId;
                            if (catId) {
                                options += `<option value="${catId}">${catName}</option>`;
                            }
                        });
                        toastr.success('Categories loaded successfully');
                    } else {
                        options += '<option value="" disabled>No categories available</option>';
                        toastr.warning('No categories returned from GlobalTix API');
                    }
                    categorySelect.html(options).prop('disabled', false);
                },
                error: function(xhr) {
                    console.error('GlobalTix categories fetch error', xhr);
                    categorySelect.html('<option value="">All Categories</option>').prop('disabled', false);
                    toastr.error('Failed to load categories from GlobalTix API');
                }
            });
        }

        // ==================== GLOBALTIX: Load Cities on Country Change ====================
        $(document).on('change', '#gtCountryCode', function() {
            const countryCode = $(this).val();
            const citySelect = $('#gtCity');
            citySelect.prop('disabled', true).html('<option>Loading...</option>');

            $.ajax({
                url: "{{ route('tempProduct.globaltix.cities') }}",
                type: "GET",
                data: { countryCode: countryCode },
                success: function(response) {
                    let options = '<option value="" selected>Select City (Optional)</option>';
                    if(response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(city) {
                            options += `<option value="${city.id}">${city.name}</option>`;
                        });
                    } else {
                        options += '<option value="" disabled>No cities available</option>';
                    }
                    citySelect.html(options).prop('disabled', false);
                },
                error: function() {
                    citySelect.html('<option value="">Failed to load</option>').prop('disabled', false);
                    toastr.error('Failed to load cities');
                }
            });
        });

        // ==================== GET SELECTED IDS ====================
        function getSelectedIds() {
            let ids = [];
            $('.row-checkbox:checked').each(function() {
                ids.push($(this).val());
            });
            return ids;
        }

        // ==================== BULK IMPORT ====================
        $('#bulkImport').on('click', function() {
            let ids = getSelectedIds();
            const btn = $(this);

            if (!ids.length) {
                toastr.warning('No products selected.');
                return;
            }

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Queueing import...');

            $.ajax({
                url: "{{ route('tempProduct.bulkImport') }}",
                type: "POST",
                contentType: "application/json",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                data: JSON.stringify({ ids: ids }),
                success: function(resp) {
                    toastr.success(resp.message || 'Bulk import queued. You can leave this page while it runs.');
                    $('#selectAll').prop('checked', false);
                    $('.row-checkbox').prop('checked', false);
                    dTable.ajax.reload(null, false);
                },
                error: function(xhr) {
                    let message = 'Bulk import could not be queued.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    toastr.error(message);
                },
                complete: function() {
                    btn.prop('disabled', false).html('Bulk Import');
                }
            });
        });

        // ==================== BULK DELETE ====================
        $('#bulkDelete').on('click', function() {
            let ids = getSelectedIds();

            if (!ids.length) {
                toastr.warning('Please select products to delete.');
                return;
            }

            Swal.fire({
                title: 'Delete selected products?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete all'
            }).then(result => {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: "{{ route('tempProduct.bulkDelete') }}",
                    type: "DELETE",
                    contentType: "application/json",
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                    },
                    data: JSON.stringify({ ids: ids }),
                    success: function(resp) {
                        toastr.success(resp.message || 'Selected products deleted successfully.');
                        dTable.ajax.reload(null, false);
                        $('#selectAll').prop('checked', false);
                        $('.row-checkbox').prop('checked', false);
                    },
                    error: function(xhr) {
                        let message = 'Bulk delete failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        toastr.error(message);
                    }
                });
            });
        });

        // ==================== BOKUN SEARCH ====================
        $('#bokunSearchBtn').on('click', function() {
            $('.error-text').text('');
            const btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Queueing...');

            $.ajax({
                url: "{{ route('tempProduct.search') }}",
                method: "POST",
                data: $('#bokunSearchForm').serialize(),
                success: function(resp) {
                    toastr.success(resp.message || 'Bokun search queued. Results will appear when background processing completes.');
                    dTable.ajax.reload(null, false);
                    $('#bokunSearchModal').modal('hide');
                    $('#bokunSearchForm')[0].reset();
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            $('.' + field + '_error').text(errors[field][0]);
                        });
                        toastr.warning('Please fix the highlighted fields.');
                    } else {
                        let message = 'System error. Please contact support.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        toastr.error(message);
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="bi bi-search me-1"></i>Search & Import');
                }
            });
        });

        // ==================== GLOBALTIX SEARCH ====================
        $('#globaltixSearchBtn').on('click', function() {
            $('.error-text').text('');
            const btn = $(this);

            // Validate country code if Product API ID is not provided
            if (!$('#gtProductApiId').val().trim() && !$('#gtCountryCode').val()) {
                $('.countryCode_error').text('Country code is required for GlobalTix searches');
                toastr.warning('Please select a country code for GlobalTix');
                return;
            }

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Queueing...');

            $.ajax({
                url: "{{ route('tempProduct.search') }}",
                method: "POST",
                data: $('#globaltixSearchForm').serialize(),
                success: function(resp) {
                    toastr.success(resp.message || 'GlobalTix search queued. Results will appear when background processing completes.');
                    dTable.ajax.reload(null, false);
                    $('#globaltixSearchModal').modal('hide');
                    $('#globaltixSearchForm')[0].reset();
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            $('.' + field + '_error').text(errors[field][0]);
                        });
                        toastr.warning('Please fix the highlighted fields.');
                    } else {
                        let message = 'System error. Please contact support.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        toastr.error(message);
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="bi bi-search me-1"></i>Search & Import');
                }
            });
        });

        // ==================== OPEN SYNC GLOBALTIX MODAL ====================
        window.openSyncGlobalTixModal = function() {
            const today = new Date();
            const sevenDaysAgo = new Date(today);
            sevenDaysAgo.setDate(today.getDate() - 7);
            $('#syncStartDate').val(sevenDaysAgo.toISOString().split('T')[0]);
            $('#syncEndDate').val(today.toISOString().split('T')[0]);
            $('#syncStartDate').attr('max', today.toISOString().split('T')[0]);
            $('.startDate_error').text('');
            $('.endDate_error').text('');
            $('#syncGtProgressSection').addClass('d-none');
            $('#syncGtProgressBar').css('width', '0%').removeClass('bg-success bg-secondary').addClass('bg-info');
            $('#syncGtProgressPercent').text('0%');
            $('#syncGtProgressDetails').text('');
            $('#syncGlobalTixBtn').prop('disabled', false).html('<i class="bi bi-arrow-repeat me-1"></i> Sync New Products');
            $('#syncGlobalTixCloseBtn, #syncGlobalTixHeaderCloseBtn').prop('disabled', false);
            $('#syncGlobalTixModal').modal('show');
        };

        // Auto-adjust endDate when startDate changes (max 7 days range)
        $('#syncStartDate').on('change', function() {
            const startDate = new Date($(this).val());
            const today = new Date();

            if (startDate > today) {
                toastr.warning('Start date cannot be in the future.');
                $(this).val(today.toISOString().split('T')[0]);
                return;
            }

            const maxEnd = new Date(startDate);
            maxEnd.setDate(startDate.getDate() + 7);
            const endDate = maxEnd > today ? today : maxEnd;
            $('#syncEndDate').val(endDate.toISOString().split('T')[0]);
        });

        // Validate endDate is not more than 7 days from startDate
        $('#syncEndDate').on('change', function() {
            const startDate = new Date($('#syncStartDate').val());
            const endDate = new Date($(this).val());
            const diffDays = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));

            if (diffDays > 7) {
                toastr.warning('Date range should not exceed 7 days for best results.');
            }
            if (diffDays < 0) {
                toastr.warning('End date cannot be before start date.');
                const maxEnd = new Date(startDate);
                maxEnd.setDate(startDate.getDate() + 7);
                const today = new Date();
                const corrected = maxEnd > today ? today : maxEnd;
                $(this).val(corrected.toISOString().split('T')[0]);
            }
        });

        // Interactive GlobalTix new products sync with real-time progress
        $('#syncGlobalTixBtn').on('click', function() {
            const btn = $(this);
            const closeBtn = $('#syncGlobalTixCloseBtn, #syncGlobalTixHeaderCloseBtn');
            const originalText = btn.html();
            const startDate = $('#syncStartDate').val();
            const endDate = $('#syncEndDate').val();

            if (!startDate || !endDate) {
                toastr.warning('Please select both start and end dates.');
                return;
            }

            if (new Date(startDate) > new Date(endDate)) {
                toastr.warning('Start date cannot be after end date.');
                return;
            }

            $('.startDate_error').text('');
            $('.endDate_error').text('');

            const progressSection = $('#syncGtProgressSection');
            const progressStatus = $('#syncGtProgressStatus');
            const progressPercent = $('#syncGtProgressPercent');
            const progressBar = $('#syncGtProgressBar');
            const progressDetails = $('#syncGtProgressDetails');

            progressSection.removeClass('d-none');
            progressBar.css('width', '5%').removeClass('bg-success bg-secondary').addClass('bg-info').attr('aria-valuenow', 5);
            progressPercent.text('5%');
            progressStatus.text('Querying GlobalTix API for new products...');
            progressDetails.text(`Date range: ${startDate} to ${endDate}`);

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Searching...');
            closeBtn.prop('disabled', true);

            // Step 1: Query GlobalTix API for product IDs in date range
            $.ajax({
                url: "{{ route('tempProduct.globaltix.newProductIds') }}",
                method: "POST",
                data: {
                    startDate: startDate,
                    endDate: endDate,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(resp) {
                    if (!resp.success || !resp.productIds || resp.productIds.length === 0) {
                        progressBar.css('width', '100%').removeClass('bg-info').addClass('bg-secondary');
                        progressPercent.text('100%');
                        progressStatus.text('No new products found.');
                        progressDetails.text(resp.message || `No GlobalTix products were published between ${startDate} and ${endDate}.`);
                        toastr.info(resp.message || 'No new GlobalTix products found for the selected date range.');
                        btn.prop('disabled', false).html(originalText);
                        closeBtn.prop('disabled', false);
                        return;
                    }

                    const productIds = resp.productIds;
                    const totalProducts = productIds.length;
                    progressStatus.text(`Found ${totalProducts} new GlobalTix product(s). Importing...`);
                    progressBar.css('width', '15%').attr('aria-valuenow', 15);
                    progressPercent.text('15%');

                    // Step 2: Chunk into batches of 5 for optimal performance & responsiveness
                    const chunkSize = 5;
                    const chunks = [];
                    for (let i = 0; i < totalProducts; i += chunkSize) {
                        chunks.push(productIds.slice(i, i + chunkSize));
                    }

                    let processedCount = 0;
                    let createdTotal = 0;
                    let updatedTotal = 0;
                    let skippedTotal = 0;
                    let failedTotal = 0;

                    const processNextChunk = (index) => {
                        if (index >= chunks.length) {
                            progressBar.css('width', '100%').removeClass('bg-info').addClass('bg-success');
                            progressPercent.text('100%');
                            progressStatus.text('Sync completed successfully!');
                            progressDetails.html(`<strong>Summary:</strong> ${createdTotal} created, ${updatedTotal} updated, ${skippedTotal} skipped, ${failedTotal} failed.`);
                            
                            toastr.success(`GlobalTix sync finished: ${createdTotal + updatedTotal} product(s) ready for review!`);
                            dTable.ajax.reload(null, false);
                            
                            btn.prop('disabled', false).html(originalText);
                            closeBtn.prop('disabled', false);
                            
                            setTimeout(() => {
                                $('#syncGlobalTixModal').modal('hide');
                                progressSection.addClass('d-none');
                            }, 2000);
                            return;
                        }

                        const currentChunk = chunks[index];
                        const chunkNumber = index + 1;
                        progressStatus.text(`Importing chunk ${chunkNumber} of ${chunks.length} (${processedCount}/${totalProducts} products)...`);

                        $.ajax({
                            url: "{{ route('tempProduct.globaltix.processChunk') }}",
                            method: "POST",
                            data: {
                                ids: currentChunk,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(chunkResp) {
                                createdTotal += chunkResp.created || 0;
                                updatedTotal += chunkResp.updated || 0;
                                skippedTotal += chunkResp.skipped || 0;
                                failedTotal  += chunkResp.failed || 0;
                                processedCount += currentChunk.length;

                                const pct = Math.round(15 + ((processedCount / totalProducts) * 80));
                                progressBar.css('width', pct + '%').attr('aria-valuenow', pct);
                                progressPercent.text(pct + '%');
                                progressDetails.text(`Processed: ${processedCount}/${totalProducts} products (${createdTotal} new, ${updatedTotal} updated)...`);

                                processNextChunk(index + 1);
                            },
                            error: function(xhr) {
                                failedTotal += currentChunk.length;
                                processedCount += currentChunk.length;
                                console.error('Chunk failed', xhr);
                                processNextChunk(index + 1);
                            }
                        });
                    };

                    processNextChunk(0);
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(originalText);
                    closeBtn.prop('disabled', false);
                    progressSection.addClass('d-none');

                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        Object.keys(errors).forEach(function(field) {
                            $('.' + field + '_error').text(errors[field][0]);
                        });
                        toastr.warning('Please fix the highlighted fields.');
                    } else {
                        let message = 'Failed to fetch GlobalTix product IDs.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                        toastr.error(message);
                    }
                }
            });
        });

        // ==================== VIEW PRODUCT ====================
        window.viewProduct = function(id, event) {
            if (event) event.preventDefault();

            let url = "{{ route('tempProduct.show', ':id') }}";
            url = url.replace(':id', id);

            $('#viewModal').modal('show');
            $('#viewModal .modal-body').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading product details...</p>
                </div>
            `);

            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    const productData = response.fulldetails.data;
                    const cancellationPolicyHtml = renderCancellationPolicy(productData.cancellation_policy);

                    let html = `
<div class="card bg-white text-dark border-0 rounded-4 shadow-lg overflow-hidden">
    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom border-secondary sticky-top shadow-sm" style="background-color: #ffffff; z-index: 1056;">
        <h5 class="mb-0 fw-semibold">View Import Products</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div style="overflow-y: auto; max-height: calc(100vh - 160px);">
        <div class="p-4">
            <div class="mb-4">
                <h6 class="text-uppercase text-secondary mb-3">Product Details</h6>
                <div class="row g-4">
                    <div class="col-md-4">
                        <img id="mainImage"
                            src="${escapeHtml(productData.thumbnail || 'placeholder.jpg')}"
                            alt="${escapeHtml(productData.title || 'Product')}"
                            class="img-fluid rounded-3 mb-3 shadow-sm"
                            style="object-fit: cover; width:100%; height:220px;">
                        <div class="d-flex gap-2 flex-wrap">
                            ${(productData.media?.length ? productData.media : [productData.thumbnail])
                                .filter(img => img)
                                .map(img => `<img src="${escapeHtml(img)}" alt="Thumbnail" onclick="document.getElementById('mainImage').src='${escapeHtml(img)}'" class="rounded-2 border border-light" style="width:60px;height:60px;object-fit:cover;cursor:pointer;">`).join('')}
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h3 class="fw-bolder mb-2">${escapeHtml(productData.title || 'Untitled Product')}</h3>
                        <p class="text-muted small mb-3">
                            ${productData.description?.length > 300 ? escapeHtml(productData.description.substring(0, 300)) + '...' : escapeHtml(productData.description || 'No detailed summary available.')}
                        </p>
                        <div class="d-flex flex-wrap gap-2 mb-4 pt-2">
                            <span class="badge bg-primary rounded-pill">${escapeHtml(productData.product_category || 'Category N/A')}</span>
                            <span class="badge bg-secondary rounded-pill">Activity: ${formatEnum(productData.activityType)}</span>
                            <span class="badge bg-info text-dark rounded-pill">Booking: ${formatEnum(productData.bookingType)}</span>
                            <span class="badge ${productData.published ? 'bg-success' : 'bg-danger'} text-white rounded-pill">Published: ${productData.published ? 'Yes' : 'No'}</span>
                        </div>
                        <div class="row small border-top pt-3 border-light">
                            <div class="col-sm-6 col-md-4">
                                <p class="mb-1 text-uppercase text-muted">Duration:</p>
                                <p class="mb-3 fw-semibold">${escapeHtml(productData.features?.Duration || 'N/A')}</p>
                                <p class="mb-1 text-uppercase text-muted">Difficulty:</p>
                                <p class="mb-3 fw-semibold">${escapeHtml(productData.features?.Difficulty || 'N/A')}</p>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <p class="mb-1 text-uppercase text-muted">Pickup Service:</p>
                                <p class="mb-3 fw-semibold">${escapeHtml(productData.features?.['Pickup Service'] || 'N/A')}</p>
                                <p class="mb-1 text-uppercase text-muted">Private Tour:</p>
                                <p class="mb-3 fw-semibold">${escapeHtml(productData.features?.['Private Tour'] || 'No')}</p>
                            </div>
                            <div class="col-sm-12 col-md-4">
                                <p class="mb-1 text-uppercase text-muted">Capacity Type:</p>
                                <p class="mb-3 fw-semibold">${formatEnum(productData.capacityType)}</p>
                                <p class="mb-1 text-uppercase text-muted">Pass Capacity:</p>
                                <p class="mb-3 fw-semibold">${escapeHtml(productData.passCapacity || 'N/A')}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4 border-top pt-4 border-light">
                <h6 class="text-uppercase text-secondary mb-3">Pricing & Vendor</h6>
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h3 class="text-success fw-bolder mb-1">$${escapeHtml(productData.tickets?.[0]?.price || 'N/A')}</h3>
                        <p class="small text-muted mb-0">Currency: ${escapeHtml(productData.vendor?.currency || 'EUR')}</p>
                    </div>
                    <div class="col-md-6 text-md-start pt-3 pt-md-0">
                        <p class="fw-bold fs-5 mb-1">${escapeHtml(productData.vendor?.name || 'Vendor N/A')}</p>
                        <p class="small mb-1 text-info">⭐ ${escapeHtml(productData.vendor?.rating || 0)} (${escapeHtml(productData.vendor?.reviews || 0)} reviews)</p>
                        ${productData.vendor?.link ? `<a href="${escapeHtml(productData.vendor.link)}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-info btn-sm mt-2">Visit Vendor <i class="bi bi-box-arrow-up-right"></i></a>` : ''}
                    </div>
                </div>
            </div>

            <div class="mb-4 border-top pt-4 border-light">
                <h6 class="text-uppercase text-secondary mb-3">Reviews</h6>
                <div class="d-flex align-items-center">
                    <h4 class="mb-1 text-warning me-3">⭐ ${escapeHtml(productData.reviewRating || 0)}</h4>
                    <p class="small text-muted mb-0">${escapeHtml(productData.reviewCount || 0)} reviews</p>
                </div>
            </div>

            <div class="mb-4 border-top pt-4 border-light">
                <h6 class="text-uppercase text-secondary mb-3">Location Information</h6>
                <p class="mb-1"><strong>Country:</strong> ${escapeHtml(productData.location?.country || 'N/A')}</p>
                <p class="mb-1"><strong>City:</strong> ${escapeHtml(productData.location?.city || 'N/A')}</p>
                <p class="mb-1"><strong>Timezone:</strong> ${escapeHtml(productData.time_zone || 'N/A')}</p>
            </div>

            <div class="accordion accordion-flush" id="bokunDetails">
                ${[
                    ['Highlights', productData.highlights],
                    ['Inclusions', productData.inclusions],
                    ['Exclusions', productData.exclusions],
                    ['Itinerary', productData.itinerary],
                    ['Why This Tour', productData.why_things_to_do],
                    ['Cancellation Policy', cancellationPolicyHtml],
                    ['Know Before You Go', productData.know_before_you_go],
                ].map(([title, content], i) => {
                    let bodyContent;
                    if (title === 'Itinerary' && Array.isArray(content) && content.length > 0) {
                        bodyContent = content.map((item, index) => `
                            <div class="itinerary-step mb-3 ps-3">
                                <h6 class="text-dark fw-bold mb-0">${index + 1}. ${escapeHtml(item.title || 'Step')}</h6>
                                <p class="small text-muted mb-0">${escapeHtml(item.description || 'Details unavailable')}</p>
                            </div>
                        `).join('');
                    } else if (title === 'Cancellation Policy') {
                        bodyContent = content;
                    } else if (content) {
                        bodyContent = escapeHtml(content);
                    } else {
                        bodyContent = 'No data available';
                    }
                    return `
                        <div class="accordion-item bg-white border-light">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-white text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#section${i}" aria-expanded="false" aria-controls="section${i}">
                                    ${escapeHtml(title)}
                                </button>
                            </h2>
                            <div id="section${i}" class="accordion-collapse collapse" data-bs-parent="#bokunDetails">
                                <div class="accordion-body small text-muted">${bodyContent}</div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>

            <div class="border-top pt-4 mt-4 border-light">
                <h6 class="text-uppercase text-secondary mb-3">Technical Data</h6>
                <p class="mb-1 small"><strong>Cutoff Time:</strong> ${escapeHtml(productData.cutoffType || 'N/A')} (${escapeHtml(productData.cutoffReferenceHour || '00')}:${escapeHtml(productData.cutoffReferenceMinute || '00')})</p>
                <p class="mb-1 small"><strong>Passport Required:</strong> ${productData.passportRequired ? 'Yes' : 'No'}</p>
                <p class="mb-1 small"><strong>Booking Questions:</strong> ${escapeHtml(productData.bookingQuestions?.length || 0)}</p>
            </div>
        </div>
    </div>
</div>`;

                    $('#viewModal .modal-body').html(html);
                },
                error: function(err) {
                    toastr.error('Failed to fetch product details.');
                    console.error(err);
                    $('#viewModal').modal('hide');
                }
            });
        };

        // ==================== DELETE CONFIRM ====================
        window.showDeleteConfirm = function(id, e) {
            if (e) e.preventDefault();
            Swal.fire({
                title: 'Are you sure you want to delete this record?',
                text: 'If you delete this, it will be gone forever.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!',
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteItem(id);
                }
            });
        };

        function deleteItem(id) {
            let url = "{{ route('tempProduct.destroy', ':id') }}";
            let csrfToken = '{{ csrf_token() }}';

            $.ajax({
                type: "DELETE",
                url: url.replace(':id', id),
                headers: { 'X-CSRF-TOKEN': csrfToken },
                success: function(resp) {
                    dTable.ajax.reload(null, false);
                    if (resp.success || resp['t-success']) {
                        toastr.success(resp.message || 'Product deleted successfully.');
                    } else {
                        toastr.error(resp.message || 'Failed to delete product.');
                    }
                },
                error: function(error) {
                    toastr.error('An error occurred. Please try again.');
                }
            });
        }

        // ==================== BOKUN ACTIVE ACTIVITIES ====================
        window.syncActiveBokunActivities = function() {
            const btn = $('#bokunActiveBtn');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Queueing...');

            $.ajax({
                url: "{{ route('tempProduct.bokun.queueActive') }}",
                type: "POST",
                success: function(resp) {
                    toastr.success(resp.message || 'Bokun active activities import queued.');
                    dTable.ajax.reload(null, false);
                },
                error: function(err) {
                    let message = 'Failed to queue Bokun active activities import.';
                    if (err.responseJSON && err.responseJSON.message) {
                        message = err.responseJSON.message;
                    }
                    toastr.error(message);
                    console.error(err);
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="bi bi-activity me-1"></i> Bokun Active Activities');
                }
            });
        };

        // ==================== UTILITY FUNCTIONS ====================
        const formatEnum = (value) => {
            if (!value) return 'N/A';
            return value.toString().toLowerCase().split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
        };

        const escapeHtml = (text) => {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        };

        const renderCancellationPolicy = (policy) => {
            if (!policy) {
                return '<p class="text-muted">No Cancellation Policy Available</p>';
            }
            if (typeof policy === 'string') {
                return `<p class="mb-0">${escapeHtml(policy)}</p>`;
            }
            if (Array.isArray(policy) && policy.length > 0) {
                return policy.map(item => {
                    if (typeof item === 'string') {
                        return `<p class="mb-2">${escapeHtml(item)}</p>`;
                    }
                    return `
                        <div class="mb-3 border-start border-3 border-warning ps-3">
                            <h6 class="text-dark fw-bold mb-1">${escapeHtml(item.title || item.name || 'Policy Rule')}</h6>
                            <p class="small text-muted mb-0">${escapeHtml(item.description || item.text || item.details || 'Details unavailable')}</p>
                            ${item.percentage ? `<span class="badge bg-warning text-dark">Refund: ${escapeHtml(item.percentage)}%</span>` : ''}
                            ${item.hours ? `<span class="badge bg-info text-dark ms-1">Hours: ${escapeHtml(item.hours)}</span>` : ''}
                        </div>
                    `;
                }).join('');
            }
            if (typeof policy === 'object' && policy !== null) {
                let html = '<div class="cancellation-policy-details">';
                const fields = [
                    { key: 'type', label: 'Policy Type' },
                    { key: 'description', label: 'Description' },
                    { key: 'refundable', label: 'Refundable' },
                    { key: 'deadline', label: 'Cancellation Deadline' },
                    { key: 'penalty', label: 'Penalty' },
                    { key: 'refundPercentage', label: 'Refund Percentage' },
                    { key: 'hoursBeforeStart', label: 'Hours Before Start' }
                ];
                let hasContent = false;
                fields.forEach(({ key, label }) => {
                    if (policy[key] !== undefined && policy[key] !== null) {
                        hasContent = true;
                        let value = policy[key];
                        if (typeof value === 'boolean') value = value ? 'Yes' : 'No';
                        if (typeof value === 'string' && value.includes('_')) value = formatEnum(value);
                        html += `<p class="mb-2"><strong>${label}:</strong> ${escapeHtml(value)}</p>`;
                    }
                });
                if (!hasContent) {
                    Object.entries(policy).forEach(([key, value]) => {
                        if (value !== null && value !== undefined) {
                            const formattedKey = formatEnum(key);
                            let formattedValue;
                            if (typeof value === 'boolean') formattedValue = value ? 'Yes' : 'No';
                            else if (typeof value === 'object') formattedValue = escapeHtml(JSON.stringify(value, null, 2));
                            else formattedValue = escapeHtml(value);
                            html += `<p class="mb-2"><strong>${formattedKey}:</strong> ${formattedValue}</p>`;
                        }
                    });
                }
                html += '</div>';
                return html;
            }
            return '<p class="text-muted">No Cancellation Policy Data Available</p>';
        };

        // ==================== LIVE GLOBALTIX RAW MODAL ====================
        let currentLiveRawId = null;
        let currentLiveRawData = null;

        window.viewLiveGlobalTixRaw = function(id, apiId, platform, event) {
            if (event) event.preventDefault();
            currentLiveRawId = id;

            $('#gtRawModalTitle').text('Loading Live Activity...');
            $('#gtRawPlatformBadge').text(platform ? platform.toUpperCase() : 'GLOBALTIX');
            $('#gtRawDbIdBadge').text(`Temp DB ID: #${id}`);
            $('#gtRawApiIdBadge').text(`API ID: ${apiId || id}`);
            $('#gtRawCityBadge').text('City: -');

            $('#gtRawLoading').removeClass('d-none');
            $('#gtRawError').addClass('d-none');
            $('#gtRawTabContent').addClass('d-none');
            $('#globaltixRawModal').modal('show');

            // Set default date range for timeslots (today to +14 days)
            const today = new Date().toISOString().split('T')[0];
            const next14 = new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            $('#gtTimeslotStartDate').val(today);
            $('#gtTimeslotEndDate').val(next14);

            let url = "{{ route('tempProduct.liveRaw', ':id') }}".replace(':id', id);

            $.ajax({
                url: url,
                type: 'GET',
                success: function(resp) {
                    $('#gtRawLoading').addClass('d-none');

                    if (!resp.success) {
                        $('#gtRawErrorMsg').text(resp.message || 'Failed to retrieve live data.');
                        $('#gtRawError').removeClass('d-none');
                        return;
                    }

                    currentLiveRawData = resp;
                    $('#gtRawTabContent').removeClass('d-none');
                    renderLiveRawModalContent(resp);

                    if (typeof dTable !== 'undefined') {
                        dTable.ajax.reload(null, false);
                    }
                },
                error: function(xhr) {
                    $('#gtRawLoading').addClass('d-none');
                    let msg = 'Failed to connect to provider live API.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $('#gtRawErrorMsg').text(msg);
                    $('#gtRawError').removeClass('d-none');
                }
            });
        };

        $('#globaltixRawModal').on('hidden.bs.modal', function () {
            if (typeof dTable !== 'undefined') {
                dTable.ajax.reload(null, false);
            }
        });

        window.refreshCurrentLiveRawData = function() {
            if (currentLiveRawId) {
                const apiId = currentLiveRawData?.product?.product_api_id || currentLiveRawId;
                const platform = currentLiveRawData?.platform || 'globaltix';
                window.viewLiveGlobalTixRaw(currentLiveRawId, apiId, platform);
            }
        };

        function renderLiveRawModalContent(data) {
            const product = data.product || {};
            const info = data.product_info || {};
            const options = data.product_options || [];
            const ticketTypes = data.ticket_types || [];
            const availabilities = data.availability || [];

            $('#gtRawModalTitle').text(product.title || info.name || info.title || 'Live Activity Details');
            $('#gtRawDbIdBadge').text(`Temp DB ID: #${product.id || currentLiveRawId || 'N/A'}`);
            $('#gtRawApiIdBadge').text(`API ID: ${product.product_api_id || currentLiveRawId || 'N/A'}`);
            $('#gtRawCityBadge').text(`City: ${product.city || info.city || 'N/A'}`);

            // 1. Render Options & Ticket Types
            renderLiveOptions(options, ticketTypes);

            // 2. Populate Timeslot Ticket Select & Initial Timeslots
            populateTimeslotTicketSelect(ticketTypes);
            renderTimeslotsTable(availabilities);

            // 3. Render Product Info
            renderLiveInfo(info, product);

            // 4. Render Raw JSON
            const rawPretty = JSON.stringify(data.raw_response || data, null, 2);
            $('#gtRawJsonCode').text(rawPretty);
        }

        function renderLiveOptions(options, ticketTypes) {
            const container = $('#gtOptionsContainer');
            container.empty();

            const optionsList = Array.isArray(options) ? options : (options ? [options] : []);
            $('#gtOptionsCountBadge').text(`${optionsList.length} Option(s)`);

            if (optionsList.length === 0) {
                container.html('<div class="alert alert-warning">No options found in live API response.</div>');
                return;
            }

            optionsList.forEach((opt, idx) => {
                const optId = opt.id || `opt-${idx}`;
                const optName = opt.name || opt.title || `Option ${idx + 1}`;
                const tickets = opt.ticketType || [];

                let ticketsHtml = '';
                if (Array.isArray(tickets) && tickets.length > 0) {
                    ticketsHtml = `
                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="small text-uppercase text-secondary">
                                        <th>Ticket ID</th>
                                        <th>Ticket Type</th>
                                        <th>Recommended Selling</th>
                                        <th>Selling Price</th>
                                        <th>Nett Price</th>
                                        <th>Original Price</th>
                                        <th>Currency</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tickets.map(t => `
                                        <tr>
                                            <td class="fw-bold text-primary">#${escapeHtml(t.id || 'N/A')}</td>
                                            <td class="fw-semibold">${escapeHtml(t.name || 'Standard')}</td>
                                            <td class="text-success fw-bold">${t.recommendedSellingPrice !== undefined && t.recommendedSellingPrice !== null ? escapeHtml(t.recommendedSellingPrice) : '<span class="text-muted">-</span>'}</td>
                                            <td>${t.sellingPrice !== undefined && t.sellingPrice !== null ? escapeHtml(t.sellingPrice) : '<span class="text-muted">-</span>'}</td>
                                            <td class="text-warning-emphasis">${t.nettPrice !== undefined && t.nettPrice !== null ? escapeHtml(t.nettPrice) : '<span class="text-muted">-</span>'}</td>
                                            <td class="text-muted text-decoration-line-through">${t.originalPrice !== undefined && t.originalPrice !== null ? escapeHtml(t.originalPrice) : '-'}</td>
                                            <td><span class="badge bg-light text-dark border">${escapeHtml(t.currency || 'EUR')}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    ticketsHtml = '<p class="text-muted small mb-0 mt-2">No ticket types specified for this option.</p>';
                }

                const cardHtml = `
                    <div class="card border rounded-3 shadow-sm overflow-hidden mb-2">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-dark me-2">Option ID: ${escapeHtml(optId)}</span>
                                <span class="fw-bold fs-15 text-dark">${escapeHtml(optName)}</span>
                            </div>
                            <div class="d-flex gap-1">
                                ${opt.ticketValidity ? `<span class="badge bg-secondary-transparent text-secondary">${escapeHtml(opt.ticketValidity)}</span>` : ''}
                                ${opt.isCancellable ? '<span class="badge bg-success-transparent text-success">Cancellable</span>' : '<span class="badge bg-danger-transparent text-danger">Non-refundable</span>'}
                            </div>
                        </div>
                        <div class="card-body p-3">
                            ${opt.description ? `<p class="small text-muted mb-2">${escapeHtml(opt.description)}</p>` : ''}
                            ${ticketsHtml}
                        </div>
                    </div>
                `;
                container.append(cardHtml);
            });
        }

        function populateTimeslotTicketSelect(ticketTypes) {
            const select = $('#gtTimeslotTicketSelect');
            select.empty();

            if (!ticketTypes || ticketTypes.length === 0) {
                select.append('<option value="">No ticket types available</option>');
                return;
            }

            ticketTypes.forEach(t => {
                const optText = `[#${t.ticket_id}] ${t.ticket_name} (${t.option_name}) - ${t.selling_price || '-'} ${t.currency}`;
                select.append(`<option value="${escapeHtml(t.ticket_id)}">${escapeHtml(optText)}</option>`);
            });
        }

        window.fetchLiveTimeslotsForSelectedTicket = function() {
            const ticketId = $('#gtTimeslotTicketSelect').val();
            const dateFrom = $('#gtTimeslotStartDate').val();
            const dateTo   = $('#gtTimeslotEndDate').val();

            if (!ticketId) {
                toastr.warning('Please select a ticket type.');
                return;
            }

            if (!dateFrom || !dateTo) {
                toastr.warning('Please select both start and end dates.');
                return;
            }

            $('#gtTimeslotTableLoading').removeClass('d-none');
            $('#gtTimeslotsTableBody').empty();

            $.ajax({
                url: "{{ route('tempProduct.liveAvailability') }}",
                type: "POST",
                data: {
                    ticket_id: ticketId,
                    date_from: dateFrom,
                    date_to:   dateTo,
                    _token:    $('meta[name="csrf-token"]').attr("content")
                },
                success: function(resp) {
                    $('#gtTimeslotTableLoading').addClass('d-none');
                    const slots = resp.data || [];
                    renderTimeslotsTable(slots);
                },
                error: function(xhr) {
                    $('#gtTimeslotTableLoading').addClass('d-none');
                    let msg = 'Failed to fetch timeslots.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    toastr.error(msg);
                    $('#gtTimeslotsTableBody').html(`<tr><td colspan="7" class="text-center py-4 text-danger">${escapeHtml(msg)}</td></tr>`);
                }
            });
        };

        function renderTimeslotsTable(slots) {
            const tbody = $('#gtTimeslotsTableBody');
            tbody.empty();

            const slotList = Array.isArray(slots) ? slots : [];
            $('#gtTimeslotCountBadge').text(`${slotList.length} Slot(s)`);

            if (slotList.length === 0) {
                tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No availability timeslots found for the selected date range.</td></tr>');
                return;
            }

            slotList.forEach(item => {
                const isAvail = item.isAvailable !== false && (item.available === undefined || item.available > 0);
                const statusBadge = isAvail
                    ? '<span class="badge bg-success">Available</span>'
                    : '<span class="badge bg-danger">Sold Out</span>';

                const timeDisplay = item.formatted_time || item.time || (item.time_slot ? item.time_slot : 'All Day');
                const dateDisplay = item.formatted_date || item.date || 'N/A';
                const capacityDisplay = item.available !== undefined && item.available !== null ? item.available : (item.total || 'N/A');
                const sellingPrice = item.recommendedSellingPrice ?? item.sellingPrice ?? (item.price?.amount ?? '-');
                const nettPrice = item.nettPrice ?? '-';
                const currency = item.currency ?? (item.price?.currency ?? 'EUR');

                const row = `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(dateDisplay)}</td>
                        <td><span class="badge bg-light text-dark border px-2 py-1"><i class="fe fe-clock me-1 text-info"></i>${escapeHtml(timeDisplay)}</span></td>
                        <td>${statusBadge}</td>
                        <td class="fw-bold">${escapeHtml(capacityDisplay)}</td>
                        <td class="text-success fw-bold">${escapeHtml(sellingPrice)}</td>
                        <td class="text-secondary">${escapeHtml(nettPrice)}</td>
                        <td><span class="badge bg-primary-transparent text-primary">${escapeHtml(currency)}</span></td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function renderLiveInfo(info, product) {
            const container = $('#gtInfoContainer');
            container.empty();

            const title = info.name || info.title || product.title || 'Untitled';
            const desc = info.description || info.full_description || 'No description provided.';
            const city = info.city || product.city || 'N/A';
            const country = info.country || 'N/A';
            const address = info.address || info.location?.address || 'N/A';
            const category = info.category?.name || info.productCategory || 'N/A';

            const formatInclusionText = (inc) => {
                if (!inc) return '';
                if (typeof inc === 'string') return inc;
                if (typeof inc === 'object') {
                    return inc.value || inc.name || inc.label || inc.title || inc.description || JSON.stringify(inc);
                }
                return String(inc);
            };

            const formatHighlightText = (h) => {
                if (!h) return '';
                if (typeof h === 'string') return h;
                if (typeof h === 'object') {
                    return h.value || h.name || h.label || h.title || h.description || JSON.stringify(h);
                }
                return String(h);
            };

            let highlightsHtml = '';
            if (Array.isArray(info.highlights) && info.highlights.length > 0) {
                highlightsHtml = `
                    <div class="mt-3">
                        <h6 class="fw-bold small text-uppercase text-secondary mb-2">Highlights</h6>
                        <ul class="list-group list-group-flush small">
                            ${info.highlights.map(h => `<li class="list-group-item px-0 py-1"><i class="fe fe-check-circle text-success me-2"></i>${escapeHtml(formatHighlightText(h))}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            let inclusionsHtml = '';
            if (Array.isArray(info.inclusions) && info.inclusions.length > 0) {
                inclusionsHtml = `
                    <div class="mt-3">
                        <h6 class="fw-bold small text-uppercase text-secondary mb-2">Inclusions</h6>
                        <div class="d-flex flex-wrap gap-1">
                            ${info.inclusions.map(inc => `<span class="badge bg-success-transparent text-success">${escapeHtml(formatInclusionText(inc))}</span>`).join('')}
                        </div>
                    </div>
                `;
            }

            const html = `
                <div class="col-md-7">
                    <h5 class="fw-bold text-dark mb-2">${escapeHtml(title)}</h5>
                    <div class="d-flex gap-2 mb-3">
                        <span class="badge bg-primary">${escapeHtml(category)}</span>
                        <span class="badge bg-info">${escapeHtml(city)}, ${escapeHtml(country)}</span>
                    </div>
                    <p class="text-muted small" style="white-space: pre-line; line-height: 1.6;">${escapeHtml(desc)}</p>
                    ${highlightsHtml}
                    ${inclusionsHtml}
                </div>
                <div class="col-md-5">
                    <div class="card border rounded-3 p-3 bg-light">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Location & Booking Info</h6>
                        <p class="small mb-2"><strong>Database ID (Temp ID):</strong> <span class="badge bg-warning text-dark fw-bold">#${escapeHtml(product.id || currentLiveRawId || 'N/A')}</span></p>
                        <p class="small mb-2"><strong>Product API ID:</strong> <span class="badge bg-dark">${escapeHtml(product.product_api_id || 'N/A')}</span></p>
                        <p class="small mb-2"><strong>City:</strong> ${escapeHtml(city)}</p>
                        <p class="small mb-2"><strong>Country:</strong> ${escapeHtml(country)}</p>
                        <p class="small mb-2"><strong>Address:</strong> ${escapeHtml(address)}</p>
                        <p class="small mb-2"><strong>Stored Temp Price:</strong> <span class="badge bg-dark">${product.stored_price ? Number(product.stored_price).toFixed(2) : '0.00'} EUR</span></p>
                        ${info.meetingPoint ? `<p class="small mb-2"><strong>Meeting Point:</strong> ${escapeHtml(info.meetingPoint)}</p>` : ''}
                    </div>
                </div>
            `;
            container.html(html);
        }

        window.copyGtRawJson = function() {
            const code = $('#gtRawJsonCode').text();
            navigator.clipboard.writeText(code).then(() => {
                toastr.success('Raw GlobalTix JSON copied to clipboard!');
            }).catch(() => {
                toastr.info('Please select and copy the text manually.');
            });
        };
    });
</script>
