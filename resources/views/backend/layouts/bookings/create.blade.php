@extends('backend.app')

@section('title', 'Create Booking')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Create New Booking</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('booking.index') }}">Bookings</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}

    <div class="row">
        <div class="col-lg-12">
            {{-- STEP 1: Select Provider --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Step 1: Select Provider &amp; Product</h3>
                </div>
                <div class="card-body">
                    <form id="bookingCreateForm" class="row g-3">
                        @csrf

                        <div class="col-md-4">
                            <label class="form-label">Provider <span class="text-danger">*</span></label>
                            <select name="provider" id="providerSelect" class="form-control" required>
                                <option value="">— Select Provider —</option>
                                <option value="bokun">Bokun</option>
                                <option value="globaltix">GlobalTix</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Search Product <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" id="productSearch" class="form-control" placeholder="Search by product name, API ID..." minlength="2" required>
                                <button type="button" id="searchBtn" class="btn btn-primary">
                                    <i class="fa fa-search"></i> Search
                                </button>
                            </div>
                            <div id="productResults" class="mt-2" style="max-height: 300px; overflow-y: auto;"></div>
                            <input type="hidden" name="product_id" id="selectedProductId">
                        </div>
                    </form>
                </div>
            </div>

            {{-- STEP 2: Booking Details (shown after product selection) --}}
            <div class="card" id="bookingDetailsCard" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title">Step 2: Booking Details</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="selectedProductInfo">
                        {{-- Product info will be populated here --}}
                    </div>

                    <hr>

                    <form id="createBookingForm" class="row g-3">
                        @csrf
                        <input type="hidden" name="provider" id="createProvider">
                        <input type="hidden" name="product_id" id="createProductId">

                        <div class="col-12">
                            <h5 class="fw-bold">Customer Information</h5>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Full name" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Customer Email <span class="text-danger">*</span></label>
                            <input type="email" name="customer_email" class="form-control" placeholder="email@example.com" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Customer Phone</label>
                            <input type="text" name="customer_phone" class="form-control" placeholder="+1234567890">
                        </div>

                        <div class="col-12 mt-3">
                            <h5 class="fw-bold">Booking Configuration</h5>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" value="1" min="1" max="100" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Visit Date <span class="text-danger">*</span></label>
                            <input type="date" name="visit_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Visit Time</label>
                            <input type="time" name="visit_time" class="form-control">
                        </div>

                        <div class="col-md-3" id="ticketTypeGroup" style="display: none;">
                            <label class="form-label">Ticket Type / Pricing Category</label>
                            <select name="ticket_type_id" class="form-control" id="ticketTypeSelect">
                                <option value="">— Default —</option>
                            </select>
                        </div>

                        {{-- PRICE SUMMARY — shows live calculation --}}
                        <div class="col-12" id="priceSummaryGroup" style="display: none;">
                            <div class="card card-body bg-light mt-2" id="priceSummaryCard">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-center">
                                        <small class="text-muted d-block">Unit Price</small>
                                        <strong id="priceUnitDisplay" class="fs-5">—</strong>
                                    </div>
                                    <div class="col-md-1 text-center text-muted fs-4">
                                        <i class="fa fa-times"></i>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <small class="text-muted d-block">Quantity</small>
                                        <strong id="priceQtyDisplay" class="fs-5">—</strong>
                                    </div>
                                    <div class="col-md-1 text-center text-muted fs-4">
                                        <i class="fa fa-equals"></i>
                                    </div>
                                    <div class="col-md-5 text-center">
                                        <small class="text-muted d-block">Estimated Total</small>
                                        <strong id="priceTotalDisplay" class="fs-3 text-success">—</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-3">
                            <h5 class="fw-bold">Admin Settings</h5>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Status Override</label>
                            <select name="status_override" class="form-control">
                                <option value="">— Reserve only (default) —</option>
                                <option value="PAID">PAID (Already paid offline)</option>
                                <option value="CONFIRMED">CONFIRMED (Skip payment)</option>
                            </select>
                            <small class="text-muted">Only use PAID/CONFIRMED if payment has been collected offline.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Generate Payment Link</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="generate_payment_link" value="1" class="form-check-input" id="generatePaymentLink">
                                <label class="form-check-label" for="generatePaymentLink">Create Stripe checkout link for payment</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Skip Provider API</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="skip_provider" value="1" class="form-check-input" id="skipProvider">
                                <label class="form-check-label" for="skipProvider">Create local booking only (no provider API call)</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Admin Note</label>
                            <textarea name="admin_note" class="form-control" rows="2" placeholder="Internal note about this booking..."></textarea>
                        </div>

                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-success btn-lg" id="createBookingBtn">
                                <i class="fa fa-check-circle"></i> Create Booking
                            </button>
                            <a href="{{ route('booking.index') }}" class="btn btn-secondary btn-lg ms-2">
                                <i class="fa fa-arrow-left"></i> Back to Bookings
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- RESULT MODAL --}}
    <div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" id="resultModalHeader">
                    <h5 class="modal-title" id="resultModalLabel">Booking Created</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="resultModalBody">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="{{ route('booking.index') }}" class="btn btn-primary" id="resultViewAllBtn">View All Bookings</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                }
            });

            var selectedProduct = null;

            // ==================== PRODUCT SEARCH ====================
            function searchProducts() {
                var query = $('#productSearch').val().trim();
                var provider = $('#providerSelect').val();

                if (!provider) {
                    toastr.warning('Please select a provider first.');
                    return;
                }

                if (query.length < 2) {
                    toastr.warning('Please enter at least 2 characters to search.');
                    return;
                }

                $('#productResults').html(
                    '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Searching...</div>'
                );

                $.ajax({
                    url: "{{ route('admin.booking.search.products') }}",
                    type: 'GET',
                    data: {
                        q: query,
                        provider: provider,
                        limit: 20
                    },
                    success: function(resp) {
                        var products = resp.data || [];
                        if (products.length === 0) {
                            $('#productResults').html(
                                '<div class="alert alert-info mt-2">No products found. Try a different search term.</div>'
                            );
                            return;
                        }

                        var html = '<div class="list-group mt-2">';
                        $.each(products, function(i, p) {
                            var price = p.from_price ? (p.default_currency || 'EUR') + ' ' + parseFloat(p
                                .from_price).toFixed(2) : '—';
                            var providerBadge = p.provider === 'globaltix' ?
                                '<span class="badge bg-info">GlobalTix</span>' :
                                '<span class="badge bg-primary">Bokun</span>';
                            var desc = p.short_introduction ? p.short_introduction.substring(0, 80) + (
                                p.short_introduction.length > 80 ? '&hellip;' : '') : '';

                            html += '<button type="button" class="list-group-item list-group-item-action product-item" data-id="' +
                                p.id + '" data-provider="' + (p.provider || provider) +
                                '" data-title="' + $('<span>').text(p.title || '').html() +
                                '" data-price="' + (p.from_price || 0) +
                                '" data-currency="' + (p.default_currency || 'EUR') +
                                '" data-api-id="' + (p.product_api_id || '') + '">';
                            html += '<div class="d-flex w-100 justify-content-between">';
                            html += '<h6 class="mb-1">' + $('<span>').text(p.title || 'Untitled').html() +
                                '</h6>';
                            html += '<small>' + providerBadge + ' <strong>' + price + '</strong></small>';
                            html += '</div>';
                            if (desc) {
                                html += '<p class="mb-1 text-muted small">' + desc + '</p>';
                            }
                            html += '<small class="text-muted">API ID: ' + (p.product_api_id || '—') +
                                ' | Slug: ' + (p.slug || '—') + '</small>';
                            html += '</button>';
                        });
                        html += '</div>';

                        $('#productResults').html(html);

                        // Bind click handlers
                        $('.product-item').off('click').on('click', function() {
                            selectProduct($(this));
                        });
                    },
                    error: function(xhr) {
                        var msg = 'Search failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        $('#productResults').html('<div class="alert alert-danger mt-2">' + msg + '</div>');
                        toastr.error(msg);
                    }
                });
            }

            $('#searchBtn').on('click', searchProducts);
            $('#productSearch').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    searchProducts();
                }
            });

            // ==================== SELECT PRODUCT ====================
            function selectProduct(el) {
                selectedProduct = {
                    id: el.data('id'),
                    provider: el.data('provider'),
                    title: el.data('title'),
                    price: el.data('price'),
                    currency: el.data('currency'),
                    apiId: el.data('api-id'),
                };

                $('#selectedProductId').val(selectedProduct.id);
                $('#createProvider').val(selectedProduct.provider);
                $('#createProductId').val(selectedProduct.id);

                // Highlight selected
                $('.product-item').removeClass('active');
                el.addClass('active');

                // Show selected product info
                var infoHtml = '<div class="col-12">' +
                    '<div class="alert alert-success">' +
                    '<h5><i class="fa fa-check-circle"></i> Selected Product</h5>' +
                    '<table class="table table-sm mb-0">' +
                    '<tr><td style="width:120px"><strong>Title:</strong></td><td>' + $('<span>').text(selectedProduct
                        .title).html() + '</td></tr>' +
                    '<tr><td><strong>Provider:</strong></td><td>' + selectedProduct.provider.toUpperCase() +
                    '</td></tr>' +
                    '<tr><td><strong>Price:</strong></td><td>' + selectedProduct.currency + ' ' + parseFloat(
                        selectedProduct.price).toFixed(2) + '</td></tr>' +
                    '<tr><td><strong>API ID:</strong></td><td>' + (selectedProduct.apiId || '—') + '</td></tr>' +
                    '</table>' +
                    '</div>' +
                    '</div>';

                $('#selectedProductInfo').html(infoHtml);                                // Show ticket type dropdown for GlobalTix
                if (selectedProduct.provider === 'globaltix') {
                    loadTicketTypes(selectedProduct.id);
                } else {
                    $('#ticketTypeGroup').hide();
                    // For Bokun, show price summary using product's base price
                    updatePriceSummary();
                }

                // Show Step 2
                $('#bookingDetailsCard').slideDown();
            }

            // ==================== LOAD TICKET TYPES ====================
            function loadTicketTypes(productId) {
                $('#ticketTypeSelect').html(
                    '<option value="">Loading ticket types...</option>');
                $('#ticketTypeGroup').show();

                $.ajax({
                    url: "{{ url('/admin/bookings/ticket-types') }}/" + encodeURIComponent(productId),
                    type: 'GET',
                    success: function(resp) {
                        var ticketTypes = resp.data?.ticket_types || [];

                        if (ticketTypes.length === 0) {
                            $('#ticketTypeSelect').html(
                                '<option value="">— No ticket types available —</option>');
                            updatePriceSummary();
                            return;
                        }

                        var html = '<option value="">— Select Ticket Type —</option>';
                        $.each(ticketTypes, function(i, tt) {
                            var key = tt.external_id || tt.id;
                            var label = tt.label || ('Ticket #' + key);
                            var priceStr = tt.price ? tt.currency + ' ' + parseFloat(tt.price).toFixed(2) : '';
                            var ageRange = tt.age_range ? ' [' + tt.age_range + ']' : '';
                            var defaultTag = tt.is_default ? ' ★ Default' : '';
                            var text = label + (priceStr ? ' (' + priceStr + ')' : '') + ageRange + defaultTag;
                            html += '<option value="' + key + '" ' +
                                (tt.is_default ? 'selected' : '') + ' data-price="' + (tt.price || 0) +
                                '" data-currency="' + (tt.currency || 'EUR') + '">' +
                                $('<span>').text(text).html() + '</option>';
                        });
                        $('#ticketTypeSelect').html(html);

                        if (ticketTypes.length === 1) {
                            // Auto-select the only option
                            var only = ticketTypes[0];
                            $('#ticketTypeSelect').val(only.external_id || only.id);
                        }

                        updatePriceSummary();
                    },
                    error: function() {
                        $('#ticketTypeSelect').html(
                            '<option value="">— Use Default —</option>');
                        toastr.warning('Could not load ticket types. Default will be used.');
                        updatePriceSummary();
                    }
                });
            }

            // ==================== PRICE CALCULATOR ====================
            function updatePriceSummary() {
                var qty = parseInt($('input[name="quantity"]').val()) || 1;
                var currency = selectedProduct ? (selectedProduct.currency || 'EUR') : 'EUR';
                var unitPrice = 0;
                var $selectedOption = $('#ticketTypeSelect option:selected');

                if (selectedProduct && selectedProduct.provider === 'globaltix' && $selectedOption.length && $selectedOption.val()) {
                    // Use selected ticket type's price from data-price attribute
                    var attrPrice = $selectedOption.data('price');
                    var attrCurrency = $selectedOption.data('currency');
                    if (attrPrice !== undefined && attrPrice !== null) {
                        unitPrice = parseFloat(attrPrice);
                        if (attrCurrency) currency = attrCurrency;
                    }
                } else if (selectedProduct) {
                    // Use product base price for Bokun or when no ticket type selected
                    unitPrice = parseFloat(selectedProduct.price) || 0;
                }

                var total = unitPrice * qty;

                // Update the display
                if (selectedProduct) {
                    $('#priceUnitDisplay').text(currency + ' ' + unitPrice.toFixed(2));
                    $('#priceQtyDisplay').text(qty);
                    $('#priceTotalDisplay').text(currency + ' ' + total.toFixed(2));
                    $('#priceSummaryGroup').slideDown();
                } else {
                    $('#priceSummaryGroup').slideUp();
                }
            }

            // Wire up price recalculation on quantity and ticket type changes
            $(document).on('change', 'select[name="ticket_type_id"]', updatePriceSummary);
            $(document).on('input change', 'input[name="quantity"]', updatePriceSummary);

            // ==================== CREATE BOOKING ====================
            $('#createBookingForm').on('submit', function(e) {
                e.preventDefault();

                if (!selectedProduct) {
                    toastr.warning('Please search and select a product first.');
                    return;
                }

                var btn = $('#createBookingBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating Booking...');

                var formData = {
                    provider: selectedProduct.provider,
                    product_id: selectedProduct.id,
                    customer_name: $('input[name="customer_name"]').val(),
                    customer_email: $('input[name="customer_email"]').val(),
                    customer_phone: $('input[name="customer_phone"]').val(),
                    quantity: $('input[name="quantity"]').val(),
                    visit_date: $('input[name="visit_date"]').val(),
                    visit_time: $('input[name="visit_time"]').val(),
                    ticket_type_id: $('select[name="ticket_type_id"]').val() || null,
                    status_override: $('select[name="status_override"]').val() || null,
                    skip_provider: $('#skipProvider').is(':checked') ? '1' : '0',
                    admin_note: $('textarea[name="admin_note"]').val(),
                };

                $.ajax({
                    url: "{{ route('admin.booking.create.booking') }}",
                    type: 'POST',
                    data: formData,
                    success: function(resp) {
                        var booking = resp.data ? (resp.data.booking || resp.data) : {};
                        var ref = booking.reference || resp.data?.reference || '—';
                        var hasError = resp.data?.needs_attention || false;
                        var providerError = resp.data?.provider_error || null;

                        var modalHeaderClass = hasError ? 'bg-warning text-dark' : 'bg-success text-white';
                        var icon = hasError ? 'fa-exclamation-triangle' : 'fa-check-circle';
                        var title = hasError ? 'Booking Created with Warnings' : 'Booking Created Successfully';

                        var modalBody = '<div class="text-center mb-3">' +
                            '<h3><i class="fa ' + icon + '"></i> ' + title + '</h3>' +
                            '</div>' +
                            '<div class="card card-body bg-light">' +
                            '<table class="table table-sm mb-0">' +
                            '<tr><td><strong>Reference:</strong></td><td><code>' + ref + '</code></td></tr>' +
                            '<tr><td><strong>Provider:</strong></td><td>' + (selectedProduct.provider || '—')
                            .toUpperCase() + '</td></tr>' +
                            '<tr><td><strong>Product:</strong></td><td>' + $('<span>').text(selectedProduct
                                .title).html() + '</td></tr>' +
                            '<tr><td><strong>Customer:</strong></td><td>' + (formData.customer_name || '—') +
                            '</td></tr>' +
                            '<tr><td><strong>Status:</strong></td><td>' + (booking.status || 'RESERVED') +
                            '</td></tr>' +
                            '<tr><td><strong>Date:</strong></td><td>' + (formData.visit_date || '—') +
                            '</td></tr>' +
                            '<tr><td><strong>Quantity:</strong></td><td>' + (formData.quantity || '1') +
                            '</td></tr>' +
                            '</table>' +
                            '</div>';

                        if (hasError && providerError) {
                            modalBody += '<div class="alert alert-warning mt-3">' +
                                '<i class="fa fa-exclamation-triangle"></i> <strong>Provider Notice:</strong> ' +
                                $('<span>').text(providerError).html() +
                                '<br><small>The booking was created locally but the provider reservation failed. Check the booking in the management panel.</small>' +
                                '</div>';
                        }

                        modalBody += '<div class="mt-3">' +
                            '<a href="{{ url('/admin/bookings') }}/' + ref +
                            '" class="btn btn-sm btn-info view-booking-link" target="_blank">' +
                            '<i class="fa fa-eye"></i> View Booking Details' +
                            '</a>' +
                            '</div>';

                        // Check if we should offer payment link generation
                        if ($('#generatePaymentLink').is(':checked') && booking.status !== 'PAID' && booking
                            .status !== 'CONFIRMED') {
                            generatePaymentLinkAndShow(ref, modalBody, function(updatedBody) {
                                $('#resultModalHeader').attr('class',
                                'modal-header ' + modalHeaderClass);
                                $('#resultModalLabel').text(title);
                                $('#resultModalBody').html(updatedBody);
                                $('#resultModal').modal('show');
                                btn.prop('disabled', false).html(
                                    '<i class="fa fa-check-circle"></i> Create Booking');
                            });
                        } else {
                            $('#resultModalHeader').attr('class', 'modal-header ' + modalHeaderClass);
                            $('#resultModalLabel').text(title);
                            $('#resultModalBody').html(modalBody);
                            $('#resultModal').modal('show');
                            btn.prop('disabled', false).html(
                                '<i class="fa fa-check-circle"></i> Create Booking');
                        }
                    },
                    error: function(xhr) {
                        var msg = 'Failed to create booking.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        }
                        toastr.error(msg);
                        btn.prop('disabled', false).html(
                        '<i class="fa fa-check-circle"></i> Create Booking');
                    }
                });
            });

            // ==================== GENERATE PAYMENT LINK ====================
            function generatePaymentLinkAndShow(reference, currentBody, callback) {
                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(reference) + "/payment-link",
                    type: 'GET',
                    success: function(resp) {
                        var checkoutUrl = resp.data?.checkout_url || '';
                        if (checkoutUrl) {
                            var paymentHtml = '<div class="alert alert-info mt-3">' +
                                '<h6><i class="fa fa-credit-card"></i> Payment Link Generated</h6>' +
                                '<div class="input-group mt-2">' +
                                '<input type="text" class="form-control" value="' + checkoutUrl +
                                '" id="paymentLinkInput" readonly>' +
                                '<button class="btn btn-primary" type="button" onclick="copyPaymentLink()">' +
                                '<i class="fa fa-copy"></i> Copy' +
                                '</button>' +
                                '</div>' +
                                '<small class="text-muted">Send this link to the customer for payment.</small>' +
                                '</div>';
                            callback(currentBody + paymentHtml);
                        } else {
                            callback(currentBody);
                        }
                    },
                    error: function() {
                        callback(currentBody);
                    }
                });
            }

            // ==================== RESET ON MODAL HIDE ====================
            $('#resultModal').on('hidden.bs.modal', function() {
                // Optionally redirect to bookings list
                window.location.href = "{{ route('booking.index') }}";
            });
        });

        // Global function for copy button
        function copyPaymentLink() {
            var input = document.getElementById('paymentLinkInput');
            if (input) {
                input.select();
                document.execCommand('copy');
                toastr.success('Payment link copied to clipboard!');
            }
        }
    </script>
@endpush
