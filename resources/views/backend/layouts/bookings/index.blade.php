@extends('backend.app')

@section('title', 'Bookings Management')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Bookings Management</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Bookings</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}

    {{-- STATS CARDS --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Bookings</h6>
                    <h2 class="mb-0" id="totalBookings">—</h2>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h6 class="text-danger">Failed</h6>
                    <h2 class="mb-0 text-danger" id="failedBookings">—</h2>
                    <small class="text-muted">(current page)</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-warning">Needs Attention</h6>
                    <h2 class="mb-0 text-warning" id="needsAttentionBookings">—</h2>
                    <small class="text-muted">(current page)</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-success">Confirmed</h6>
                    <h2 class="mb-0 text-success" id="confirmedBookings">—</h2>
                    <small class="text-muted">(current page)</small>
                </div>
            </div>
        </div>
    </div>
    {{-- STATS CARDS --}}

    <div class="row row-sm">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h3 class="card-title">All Bookings</h3>
                    <div class="d-flex gap-2">
                        {{-- <a href="{{ route('booking.create') }}" class="btn btn-success btn-sm">
                            <i class="fa fa-plus"></i> Create Booking
                        </a> --}}
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false">
                            <i class="fa fa-filter"></i> Filters
                        </button>
                    </div>
                </div>

                {{-- FILTER SECTION --}}
                <div class="collapse" id="filterCollapse">
                    <div class="card-body border-bottom pb-2">
                        <form id="filterForm" class="row g-2 filter-form-row">
                            <div class="col-6 col-md-2">
                                <label for="filterSearch" class="visually-hidden">Search</label>
                                <input type="text" name="search" id="filterSearch" class="form-control form-control-sm" placeholder="Search ref, customer, email">
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="filterStatus" class="visually-hidden">Status</label>
                                <select name="status" id="filterStatus" class="form-control form-control-sm">
                                    <option value="">All Statuses</option>
                                    <option value="RESERVED">RESERVED</option>
                                    <option value="PAID">PAID</option>
                                    <option value="CONFIRMED">CONFIRMED</option>
                                    <option value="CANCELLED">CANCELLED</option>
                                    <option value="FAILED">FAILED</option>
                                    <option value="COMPLETED">COMPLETED</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="filterAdminStatus" class="visually-hidden">Admin Status</label>
                                <select name="admin_status" id="filterAdminStatus" class="form-control form-control-sm">
                                    <option value="">Admin Status (all)</option>
                                    <option value="open">Open</option>
                                    <option value="needs_attention">Needs Attention</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="filterProvider" class="visually-hidden">Provider</label>
                                <select name="provider_type" id="filterProvider" class="form-control form-control-sm">
                                    <option value="">All Providers</option>
                                    <option value="bokun">Bokun</option>
                                    <option value="globaltix">GlobalTix</option>
                                    <option value="supplier">Supplier</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="filterGateway" class="visually-hidden">Gateway</label>
                                <select name="payment_gateway" id="filterGateway" class="form-control form-control-sm">
                                    <option value="">All Gateways</option>
                                    <option value="stripe">Stripe</option>
                                    <option value="paypal">PayPal</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-2">
                                <div class="row g-1">
                                    <div class="col-6 col-md-12">
                                        <label for="filterDateFrom" class="visually-hidden">Date from</label>
                                        <input type="date" name="date_from" id="filterDateFrom" class="form-control form-control-sm" placeholder="From">
                                    </div>
                                    <div class="col-6 col-md-12">
                                        <label for="filterDateTo" class="visually-hidden">Date to</label>
                                        <input type="date" name="date_to" id="filterDateTo" class="form-control form-control-sm" placeholder="To">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-2">
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="failed_only" value="1" class="form-check-input" id="filterFailedOnly">
                                    <label class="form-check-label" for="filterFailedOnly">Needs admin attention</label>
                                </div>
                            </div>
                            <div class="col-6 col-md-2">
                                <label for="filterApplyBtn" class="visually-hidden">Apply filters</label>
                                <button type="submit" id="filterApplyBtn" class="btn btn-primary btn-sm w-100">Apply Filters</button>
                            </div>
                            <div class="col-6 col-md-2">
                                <button type="button" id="resetFilters" class="btn btn-light btn-sm w-100">Reset</button>
                            </div>
                        </form>
                    </div>
                </div>
                {{-- FILTER SECTION --}}

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered text-nowrap border-bottom w-100" id="bookingsTable">
                            <thead>
                                <tr>
                                    <th class="wd-5p">#</th>
                                    <th class="wd-12p">Reference</th>
                                    <th class="wd-15p">Product</th>
                                    <th class="wd-13p">Customer</th>
                                    <th class="wd-8p">Provider</th>
                                    <th class="wd-8p">Status</th>
                                    <th class="wd-8p">Admin</th>
                                    <th class="wd-8p">Amount</th>
                                    <th class="wd-8p">Created</th>
                                    <th class="wd-15p">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- DataTable AJAX data --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== MODALS ==================== --}}

    {{-- VIEW DETAILS MODAL --}}
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewModalLabel">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewModalBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- EDIT MODAL --}}
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="editReference">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="editStatus" class="form-control">
                                    <option value="">— No change —</option>
                                    <option value="RESERVED">RESERVED</option>
                                    <option value="PAID">PAID</option>
                                    <option value="CONFIRMED">CONFIRMED</option>
                                    <option value="CANCELLED">CANCELLED</option>
                                    <option value="FAILED">FAILED</option>
                                    <option value="COMPLETED">COMPLETED</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Admin Status</label>
                                <select name="admin_status" id="editAdminStatus" class="form-control">
                                    <option value="">— No change —</option>
                                    <option value="open">Open</option>
                                    <option value="needs_attention">Needs Attention</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Failure Stage</label>
                                <input type="text" name="failure_stage" id="editFailureStage" class="form-control" placeholder="e.g. provider_confirm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Failure Code</label>
                                <input type="text" name="failure_code" id="editFailureCode" class="form-control" placeholder="e.g. TIMEOUT">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Failure Reason</label>
                                <textarea name="failure_reason" id="editFailureReason" class="form-control" rows="2" placeholder="Describe the failure reason..."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Admin Note</label>
                                <textarea name="admin_note" id="editAdminNote" class="form-control" rows="3" placeholder="Internal note about this booking..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editSubmitBtn">Update Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- CANCEL MODAL --}}
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelModalLabel">Cancel Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="cancelForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="cancelReference">
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> Are you sure you want to cancel this booking? This will attempt to cancel with the provider.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cancellation Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" id="cancelReason" class="form-control" rows="3" required placeholder="Required: why is this booking being cancelled?"></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="notify" value="1" class="form-check-input" id="cancelNotify" checked>
                            <label class="form-check-label" for="cancelNotify">Notify customer</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="timeout" value="1" class="form-check-input" id="cancelTimeout">
                            <label class="form-check-label" for="cancelTimeout">Use timeout (Bokun reserved bookings)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep it</button>
                        <button type="submit" class="btn btn-danger" id="cancelSubmitBtn">
                            <i class="fa fa-ban"></i> Yes, Cancel Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- RETRY MODAL --}}
    <div class="modal fade" id="retryModal" tabindex="-1" aria-labelledby="retryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="retryModalLabel">Retry Booking Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="retryForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="retryReference">
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> This will attempt to re-confirm a PAID booking with the provider. Only use this when a payment was successful but provider confirmation failed.
                        </div>
                        <p class="mb-0"><strong>Reference:</strong> <span id="retryReferenceDisplay"></span></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="retrySubmitBtn">
                            <i class="fa fa-refresh"></i> Retry Confirmation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- REFUND MODAL --}}
    <div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="refundModalLabel">Refund Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="refundForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="refundReference">
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i> This will process a refund via the payment gateway (Stripe/PayPal). Ensure the booking is in a refundable state.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Refund Amount</label>
                            <div class="input-group">
                                <span class="input-group-text" id="refundCurrency">USD</span>
                                <input type="number" name="amount" id="refundAmount" class="form-control" step="0.01" min="0.01" placeholder="Full or partial amount">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Refund Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" id="refundReason" class="form-control" rows="3" required placeholder="Required: reason for refund"></textarea>
                        </div>
                        <p class="mb-0 text-muted small">Gateway: <strong id="refundGatewayDisplay">—</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark" id="refundSubmitBtn">
                            <i class="fa fa-undo"></i> Process Refund
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- RESOLVE MODAL --}}
    <div class="modal fade" id="resolveModal" tabindex="-1" aria-labelledby="resolveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="resolveModalLabel">Resolve Booking</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="resolveForm">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="reference" id="resolveReference">
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> Mark this booking as resolved. This will set the admin status to "resolved" and clear it from the needs-attention queue.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Resolution Note</label>
                            <textarea name="admin_note" id="resolveNote" class="form-control" rows="3" placeholder="Optional note about how this was resolved..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info text-white" id="resolveSubmitBtn">
                            <i class="fa fa-check-circle"></i> Mark Resolved
                        </button>
                    </div>
                </form>
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

            // ==================== HELPER: Render status badge ====================
            function statusBadge(status) {
                var map = {
                    'RESERVED': 'bg-secondary',
                    'PAID': 'bg-primary',
                    'CONFIRMED': 'bg-success',
                    'CANCELLED': 'bg-danger',
                    'FAILED': 'bg-danger',
                    'COMPLETED': 'bg-info',
                };
                var cls = map[status] || 'bg-secondary';
                return '<span class="badge ' + cls + '">' + (status || '—') + '</span>';
            }

            function adminStatusBadge(status) {
                var map = {
                    'open': 'bg-success',
                    'needs_attention': 'bg-warning text-dark',
                    'resolved': 'bg-info',
                };
                var cls = map[status] || 'bg-secondary';
                return '<span class="badge ' + cls + '">' + (status || 'open') + '</span>';
            }

            // Suppress DataTable default alert dialog - we handle errors via toastr
            $.fn.dataTable.ext.errMode = 'none';

            // ==================== DATATABLE ====================
            window.bookingsTable = $('#bookingsTable').DataTable({
                order: [],
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                processing: true,
                responsive: true,
                serverSide: true,
                pageLength: 25,
                language: {
                    processing: `<div class="text-center">
                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                        </div>`
                },
                pagingType: "full_numbers",
                dom: "<'row justify-content-between table-topbar'<'col-md-2 col-sm-3 px-0'B><'col-md-2 col-sm-3 px-0'l><'col-md-3 col-sm-4 px-0'f>>tipr",
                buttons: [
                    {
                        extend: 'colvis',
                        text: '<i class="fa fa-columns"></i> Columns',
                        className: 'btn btn-outline-secondary btn-sm',
                        columns: ':not(:first):not(:last)',
                        columnText: function(dt, idx, title) {
                            return title || 'Column ' + (idx + 1);
                        }
                    }
                ],
                ajax: {
                    url: "{{ route('admin.booking.data') }}",
                    type: "GET",
                    data: function(d) {
                        var filterData = $('#filterForm').serializeArray();
                        $.each(filterData, function(i, field) {
                            if (field.value !== '') {
                                d[field.name] = field.value;
                            }
                        });
                    },
                    dataSrc: function(json) {
                        // Update stats cards from the total counts returned by the server
                        $('#totalBookings').text(json.recordsTotal || 0);
                        // Per-status counts are only for the current page (shown with disclaimer in the UI)
                        if (json.data) {
                            var failed = json.data.filter(function(b) { return b.status === 'FAILED'; }).length;
                            var needsAttn = json.data.filter(function(b) { return b.admin_status === 'needs_attention'; }).length;
                            var confirmed = json.data.filter(function(b) { return b.status === 'CONFIRMED'; }).length;
                            $('#failedBookings').text(failed);
                            $('#needsAttentionBookings').text(needsAttn);
                            $('#confirmedBookings').text(confirmed);
                        }
                        return json.data;
                    },
                    error: function(xhr, error, thrown) {
                        var msg = 'Failed to load bookings data.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        }
                        console.error('DataTable error:', msg, error, thrown);
                        if (typeof toastr !== 'undefined') {
                            toastr.error(msg);
                        }
                        return [];
                    }
                },
                columns: [
                    {
                        data: 'id',
                        orderable: false,
                        render: function(data, type, row, meta) {
                            return meta.row + 1 + meta.settings._iDisplayStart;
                        }
                    },
                    {
                        data: null,
                        name: 'booking_reference',
                        orderable: true,
                        render: function(data, type, row) {
                            var ref = row.reference || '—';
                            var cc = row.confirmation_code || '';
                            var bookingRef = row.booking_reference || '';
                            var displayRef = bookingRef || ref;
                            var html = '<strong>' + displayRef + '</strong>';
                            if (cc && cc !== displayRef) {
                                html += '<br><small class="text-muted">CC: ' + cc + '</small>';
                            }
                            return html;
                        }
                    },
                    {
                        data: 'product',
                        orderable: true,
                        render: function(data) {
                            if (!data || !data.title) return '—';
                            var title = data.title;
                            if (title.length > 50) title = title.substring(0, 50) + '&hellip;';
                            return title;
                        }
                    },
                    {
                        data: 'customer',
                        orderable: true,
                        render: function(data) {
                            if (!data) return '—';
                            var name = data.name || 'N/A';
                            var email = data.email || '';
                            var html = name;
                            if (email) html += '<br><small>' + email + '</small>';
                            return html;
                        }
                    },
                    {
                        data: 'provider_type',
                        orderable: true,
                        render: function(data) {
                            if (!data) return '—';
                            var map = {
                                'bokun': '<span class="badge bg-primary">Bokun</span>',
                                'globaltix': '<span class="badge bg-info">GlobalTix</span>',
                                'supplier': '<span class="badge bg-secondary">Supplier</span>',
                            };
                            return map[data.toLowerCase()] || data;
                        }
                    },
                    {
                        data: 'status',
                        orderable: true,
                        render: function(data) {
                            return statusBadge(data);
                        }
                    },
                    {
                        data: 'admin_status',
                        orderable: true,
                        render: function(data) {
                            return adminStatusBadge(data || 'open');
                        }
                    },
                    {
                        data: 'amount',
                        orderable: true,
                        render: function(data) {
                            if (!data) return '—';
                            var cur = data.currency || 'EUR';
                            // total can be an object (from formatPrice) or a number
                            var total = '—';
                            if (data.total !== null && data.total !== undefined) {
                                if (typeof data.total === 'object' && data.total.amount !== undefined) {
                                    total = parseFloat(data.total.amount).toFixed(2);
                                } else if (typeof data.total === 'number') {
                                    total = data.total.toFixed(2);
                                } else {
                                    total = parseFloat(data.total).toFixed(2);
                                }
                            }
                            return cur + ' ' + total;
                        }
                    },
                    {
                        data: 'created_at',
                        orderable: true,
                        render: function(data) {
                            if (!data) return '—';
                            var d = new Date(data);
                            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            var ref = row.reference || row.booking_reference || '';
                            var status = (row.status || '').toUpperCase();
                            var adminStatus = (row.admin_status || 'open');
                            var canCancel = row.cancellation && row.cancellation.can_cancel;
                            var canRefund = row.cancellation && row.cancellation.can_refund;
                            var isPaid = status === 'PAID';
                            var needsAttention = adminStatus === 'needs_attention';

                            var btns = '<div class="btn-group btn-group-sm" role="group">';

                            // View (standalone page) & quick modal
                            btns += '<a href="{{ url('/admin/bookings') }}/' + ref + '/details" class="btn btn-info" title="View Details"><i class="fa fa-eye"></i></a>';
                            btns += '<button class="btn btn-outline-info btn-quick-view" data-reference="' + ref + '" title="Quick View"><i class="fa fa-search"></i></button>';

                            // Edit
                            btns += '<button class="btn btn-primary btn-edit" data-reference="' + ref + '" title="Edit"><i class="fa fa-pencil"></i></button>';

                            // Retry (only for PAID bookings)
                            if (isPaid) {
                                btns += '<button class="btn btn-success btn-retry" data-reference="' + ref + '" title="Retry Confirmation"><i class="fa fa-refresh"></i></button>';
                            }

                            // Cancel
                            if (canCancel) {
                                btns += '<button class="btn btn-danger btn-cancel" data-reference="' + ref + '" title="Cancel"><i class="fa fa-ban"></i></button>';
                            }

                            // Refund
                            if (canRefund) {
                                btns += '<button class="btn btn-warning btn-refund" data-reference="' + ref + '" title="Refund"><i class="fa fa-undo"></i></button>';
                            }

                            // Resolve (only for needs_attention)
                            if (needsAttention) {
                                btns += '<button class="btn btn-info btn-resolve" data-reference="' + ref + '" title="Mark Resolved"><i class="fa fa-check-circle"></i></button>';
                            }

                            btns += '</div>';
                            return btns;
                        }
                    }
                ]
            });

            // ==================== FILTERS ====================
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                bookingsTable.ajax.reload();
            });

            $('#resetFilters').on('click', function() {
                $('#filterForm')[0].reset();
                bookingsTable.ajax.reload();
            });

            // ==================== ACTION BUTTON BINDING (Event Delegation) ====================
            $('#bookingsTable tbody').on('click', '.btn-quick-view', function() {
                openViewModal($(this).data('reference'));
            });

            $('#bookingsTable tbody').on('click', '.btn-edit', function() {
                openEditModal($(this).data('reference'));
            });

            $('#bookingsTable tbody').on('click', '.btn-retry', function() {
                openRetryModal($(this).data('reference'));
            });

            $('#bookingsTable tbody').on('click', '.btn-cancel', function() {
                openCancelModal($(this).data('reference'));
            });

            $('#bookingsTable tbody').on('click', '.btn-refund', function() {
                openRefundModal($(this).data('reference'));
            });

            $('#bookingsTable tbody').on('click', '.btn-resolve', function() {
                openResolveModal($(this).data('reference'));
            });

            // ==================== VIEW DETAILS ====================
            function openViewModal(reference) {
                $('#viewModal').modal('show');
                $('#viewModalBody').html(
                    '<div class="text-center py-5">' +
                        '<div class="spinner-border text-primary" role="status">' +
                            '<span class="visually-hidden">Loading...</span>' +
                        '</div>' +
                        '<p class="mt-2 text-muted">Loading booking details...</p>' +
                    '</div>'
                );

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(reference),
                    type: 'GET',
                    success: function(resp) {
                        renderViewModal(resp.data || resp);
                    },
                    error: function(xhr) {
                        var msg = 'Failed to load booking details.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        $('#viewModalBody').html(
                            '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' + msg + '</div>'
                        );
                    }
                });
            }

            function renderViewModal(booking) {
                var customer = booking.customer || {};
                var amount = booking.amount || {};
                var payment = booking.payment || {};
                var failure = booking.failure || {};
                var cancellation = booking.cancellation || {};
                var refund = booking.refund || {};
                var product = booking.product || {};
                var details = booking.details || [];
                var events = booking.events || [];

                var html = '';

                // Overview card
                html += '<div class="card card-body mb-3 bg-light">';
                html += '<div class="row">';
                html += '<div class="col-md-3"><strong>Reference:</strong><br>' + (booking.reference || '—') + '</div>';
                html += '<div class="col-md-3"><strong>Confirmation Code:</strong><br>' + (booking.confirmation_code || '—') + '</div>';
                html += '<div class="col-md-3"><strong>Status:</strong><br>' + statusBadge(booking.status) + '</div>';
                html += '<div class="col-md-3"><strong>Admin Status:</strong><br>' + adminStatusBadge(booking.admin_status || 'open') + '</div>';
                html += '</div>';
                html += '<div class="row mt-2">';
                html += '<div class="col-md-3"><strong>Provider:</strong><br>' + (booking.provider_type || '—') + '</div>';
                html += '<div class="col-md-3"><strong>Channel:</strong><br>' + (booking.booking_channel || '—') + '</div>';
                html += '<div class="col-md-3"><strong>Source:</strong><br>' + (booking.booking_source || '—') + '</div>';
                html += '<div class="col-md-3"><strong>Created:</strong><br>' + (booking.created_at ? new Date(booking.created_at).toLocaleString() : '—') + '</div>';
                html += '</div>';
                html += '</div>';

                // Customer info
                html += '<h6 class="fw-bold border-bottom pb-2">Customer</h6>';
                html += '<div class="row mb-3">';
                html += '<div class="col-md-4"><strong>Name:</strong> ' + (customer.name || '—') + '</div>';
                html += '<div class="col-md-4"><strong>Email:</strong> ' + (customer.email || '—') + '</div>';
                html += '<div class="col-md-4"><strong>Phone:</strong> ' + (customer.phone || '—') + '</div>';
                html += '</div>';

                // Pricing
                html += '<h6 class="fw-bold border-bottom pb-2">Pricing</h6>';
                html += '<div class="row mb-3">';
                html += '<div class="col-md-3"><strong>Total:</strong> ' + (amount.currency || 'EUR') + ' ' + (amount.total !== null ? parseFloat(amount.total).toFixed(2) : '—') + '</div>';
                html += '<div class="col-md-3"><strong>Gateway:</strong> ' + (payment.gateway || '—') + '</div>';
                html += '<div class="col-md-3"><strong>Source:</strong> ' + (payment.source || '—') + '</div>';
                html += '<div class="col-md-3"><strong>Paid At:</strong> ' + (payment.paid_at ? new Date(payment.paid_at).toLocaleString() : '—') + '</div>';
                html += '</div>';

                // Product info
                html += '<h6 class="fw-bold border-bottom pb-2">Product</h6>';
                html += '<div class="row mb-3">';
                html += '<div class="col-md-4"><strong>Product ID:</strong> ' + (product.id || '—') + '</div>';
                html += '<div class="col-md-4"><strong>Title:</strong> ' + (product.title || '—') + '</div>';
                html += '<div class="col-md-4"><strong>Vendor ID:</strong> ' + (product.vendor_id || '—') + '</div>';
                html += '</div>';

                // Failure info (if any)
                if (failure.reason || failure.stage) {
                    html += '<h6 class="fw-bold border-bottom pb-2 text-danger">Failure Info</h6>';
                    html += '<div class="row mb-3">';
                    html += '<div class="col-md-3"><strong>Stage:</strong> ' + (failure.stage || '—') + '</div>';
                    html += '<div class="col-md-3"><strong>Code:</strong> ' + (failure.code || '—') + '</div>';
                    html += '<div class="col-md-6"><strong>Reason:</strong> ' + (failure.reason || '—') + '</div>';
                    html += '</div>';
                }

                // Cancellation info
                if (cancellation.cancelled_at) {
                    html += '<h6 class="fw-bold border-bottom pb-2 text-danger">Cancellation Info</h6>';
                    html += '<div class="row mb-3">';
                    html += '<div class="col-md-4"><strong>Cancelled At:</strong> ' + (cancellation.cancelled_at ? new Date(cancellation.cancelled_at).toLocaleString() : '—') + '</div>';
                    html += '<div class="col-md-4"><strong>By:</strong> ' + (cancellation.cancelled_by_type || '—') + '</div>';
                    html += '<div class="col-md-4"><strong>Reason:</strong> ' + (cancellation.cancellation_reason || '—') + '</div>';
                    html += '</div>';
                }

                // Refund info
                if (refund.status) {
                    html += '<h6 class="fw-bold border-bottom pb-2 text-warning">Refund Info</h6>';
                    html += '<div class="row mb-3">';
                    html += '<div class="col-md-3"><strong>Status:</strong> ' + (refund.status || '—') + '</div>';
                    html += '<div class="col-md-3"><strong>Amount:</strong> ' + (refund.currency || 'EUR') + ' ' + (refund.amount !== null ? parseFloat(refund.amount).toFixed(2) : '—') + '</div>';
                    html += '<div class="col-md-3"><strong>Gateway:</strong> ' + (refund.gateway || '—') + '</div>';
                    html += '<div class="col-md-3"><strong>Reason:</strong> ' + (refund.reason || '—') + '</div>';
                    html += '</div>';
                }

                // Booking details (line items)
                if (details.length > 0) {
                    html += '<h6 class="fw-bold border-bottom pb-2">Booking Items</h6>';
                    html += '<div class="table-responsive mb-3">';
                    html += '<table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>Item</th><th>Date</th><th>Qty</th><th>Price</th><th>Status</th></tr></thead><tbody>';
                    $.each(details, function(i, d) {
                        var item = d.product_name || d.title || ('Item ' + (i + 1));
                        var date = d.date || d.event_date || '';
                        var qty = d.quantity || d.qty || '';
                        var price = d.price || d.amount || '';
                        var st = d.status || '';
                        html += '<tr>';
                        html += '<td>' + item + '</td>';
                        html += '<td>' + date + '</td>';
                        html += '<td>' + qty + '</td>';
                        html += '<td>' + price + '</td>';
                        html += '<td>' + statusBadge(st) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                }

                // Events timeline
                if (events.length > 0) {
                    html += '<h6 class="fw-bold border-bottom pb-2">Event Timeline</h6>';
                    html += '<div class="mb-3" style="max-height: 300px; overflow-y: auto;">';
                    $.each(events, function(i, ev) {
                        var evMsg = ev.message || ev.description || '';
                        var evTime = ev.created_at ? new Date(ev.created_at).toLocaleString() : '';
                        var iconMap = {
                            'admin_updated_booking': 'fa-pencil',
                            'admin_retry_succeeded': 'fa-refresh',
                            'admin_cancelled_booking': 'fa-ban',
                            'admin_refunded_booking': 'fa-undo',
                            'admin_resolved_booking': 'fa-check-circle',
                        };
                        var icon = iconMap[ev.type || ev.event_type] || 'fa-info-circle';
                        html += '<div class="d-flex mb-2">';
                        html += '<div class="me-2 text-muted"><i class="fa ' + icon + '"></i></div>';
                        html += '<div><small class="text-muted">' + evTime + '</small><br>' + evMsg + '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }

                $('#viewModalBody').html(html);
            }

            // ==================== EDIT ====================
            function openEditModal(reference) {
                $('#editReference').val(reference);
                $('#editStatus').val('');
                $('#editAdminStatus').val('');
                $('#editFailureStage').val('');
                $('#editFailureCode').val('');
                $('#editFailureReason').val('');
                $('#editAdminNote').val('');
                $('#editModalLabel').text('Edit Booking: ' + reference);

                // Pre-fill with current values from the API
                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(reference),
                    type: 'GET',
                    success: function(resp) {
                        var booking = resp.data || resp;
                        $('#editFailureStage').val(booking.failure_stage || '');
                        $('#editFailureCode').val(booking.failure_code || '');
                        $('#editFailureReason').val(booking.failure_reason || '');
                    }
                });

                $('#editSubmitBtn').prop('disabled', false).html('Update Booking');
                $('#editModal').modal('show');
            }

            $('#editForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#editReference').val();
                var btn = $('#editSubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Updating...');

                var data = {};
                var status = $('#editStatus').val();
                var adminStatus = $('#editAdminStatus').val();
                if (status) data.status = status;
                if (adminStatus) data.admin_status = adminStatus;
                data.failure_stage = $('#editFailureStage').val() || null;
                data.failure_code = $('#editFailureCode').val() || null;
                data.failure_reason = $('#editFailureReason').val() || null;
                data.admin_note = $('#editAdminNote').val() || null;

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref),
                    type: 'PUT',
                    data: data,
                    success: function(resp) {
                        toastr.success('Booking updated successfully.');
                        $('#editModal').modal('hide');
                        bookingsTable.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        var msg = 'Update failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        }
                        toastr.error(msg);
                        btn.prop('disabled', false).html('Update Booking');
                    }
                });
            });

            // ==================== RETRY ====================
            function openRetryModal(reference) {
                $('#retryReference').val(reference);
                $('#retryReferenceDisplay').text(reference);
                $('#retrySubmitBtn').prop('disabled', false).html('<i class="fa fa-refresh"></i> Retry Confirmation');
                $('#retryModal').modal('show');
            }

            $('#retryForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#retryReference').val();
                var btn = $('#retrySubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Retrying...');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref) + "/retry",
                    type: 'POST',
                    success: function(resp) {
                        toastr.success('Booking retry completed successfully!');
                        $('#retryModal').modal('hide');
                        bookingsTable.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        var msg = 'Retry failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        toastr.error(msg);
                        btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Retry Confirmation');
                    }
                });
            });

            // ==================== CANCEL ====================
            function openCancelModal(reference) {
                $('#cancelReference').val(reference);
                $('#cancelReason').val('');
                $('#cancelNotify').prop('checked', true);
                $('#cancelTimeout').prop('checked', false);
                $('#cancelSubmitBtn').prop('disabled', false).html('<i class="fa fa-ban"></i> Yes, Cancel Booking');
                $('#cancelModal').modal('show');
            }

            $('#cancelForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#cancelReference').val();
                var reason = $('#cancelReason').val();
                if (!reason.trim()) {
                    toastr.warning('Please provide a cancellation reason.');
                    return;
                }
                var btn = $('#cancelSubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Cancelling...');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref) + "/cancel",
                    type: 'POST',
                    data: {
                        reason: reason,
                        notify: $('#cancelNotify').is(':checked') ? '1' : '0',
                        timeout: $('#cancelTimeout').is(':checked') ? '1' : '0',
                    },
                    success: function(resp) {
                        toastr.success('Booking cancelled successfully.');
                        $('#cancelModal').modal('hide');
                        bookingsTable.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        var msg = 'Cancellation failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        toastr.error(msg);
                        btn.prop('disabled', false).html('<i class="fa fa-ban"></i> Yes, Cancel Booking');
                    }
                });
            });

            // ==================== REFUND ====================
            function openRefundModal(reference) {
                $('#refundReference').val(reference);
                $('#refundAmount').val('');
                $('#refundReason').val('');
                $('#refundGatewayDisplay').text('—');
                $('#refundCurrency').text('EUR');
                $('#refundSubmitBtn').prop('disabled', false).html('<i class="fa fa-undo"></i> Process Refund');

                // Fetch latest data to pre-fill amount and gateway
                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(reference),
                    type: 'GET',
                    success: function(resp) {
                        var booking = resp.data || resp;
                        var amount = booking.amount || {};
                        var payment = booking.payment || {};
                        var total = amount.total !== null ? parseFloat(amount.total).toFixed(2) : '';
                        var cur = amount.currency || 'EUR';
                        var gateway = payment.gateway || '—';
                        $('#refundAmount').val(total);
                        $('#refundCurrency').text(cur);
                        $('#refundGatewayDisplay').text(gateway);
                    }
                });

                $('#refundModal').modal('show');
            }

            $('#refundForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#refundReference').val();
                var amount = $('#refundAmount').val();
                var reason = $('#refundReason').val();
                if (!reason.trim()) {
                    toastr.warning('Please provide a refund reason.');
                    return;
                }
                var btn = $('#refundSubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing Refund...');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref) + "/refund",
                    type: 'POST',
                    data: { amount: amount, reason: reason },
                    success: function(resp) {
                        toastr.success('Refund processed successfully.');
                        $('#refundModal').modal('hide');
                        bookingsTable.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        var msg = 'Refund failed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        toastr.error(msg);
                        btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Process Refund');
                    }
                });
            });

            // ==================== RESOLVE ====================
            function openResolveModal(reference) {
                $('#resolveReference').val(reference);
                $('#resolveNote').val('');
                $('#resolveSubmitBtn').prop('disabled', false).html('<i class="fa fa-check-circle"></i> Mark Resolved');
                $('#resolveModal').modal('show');
            }

            $('#resolveForm').on('submit', function(e) {
                e.preventDefault();
                var ref = $('#resolveReference').val();
                var note = $('#resolveNote').val();
                var btn = $('#resolveSubmitBtn');
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Resolving...');

                $.ajax({
                    url: "{{ url('/admin/bookings') }}/" + encodeURIComponent(ref) + "/resolve",
                    type: 'POST',
                    data: { admin_note: note || null },
                    success: function(resp) {
                        toastr.success('Booking marked as resolved.');
                        $('#resolveModal').modal('hide');
                        bookingsTable.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        var msg = 'Failed to resolve booking.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        toastr.error(msg);
                        btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Mark Resolved');
                    }
                });
            });

            // ==================== MODAL RESET ON HIDE ====================
            $('.modal').on('hidden.bs.modal', function() {
                $(this).find('button[type="submit"]').prop('disabled', false);
            });

        });
    </script>
@endpush
