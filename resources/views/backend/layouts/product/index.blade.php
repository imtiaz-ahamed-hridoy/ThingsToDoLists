@extends('backend.app')

@section('title', 'Products List')

@push('styles')
<style>
/* ── Fix scrollable modal header/footer ── */
.modal-dialog-scrollable .modal-content {
    display: flex !important;
    flex-direction: column !important;
    max-height: 100% !important;
}
.modal-dialog-scrollable .modal-header,
.modal-dialog-scrollable .modal-footer {
    flex-shrink: 0 !important;
}
.modal-dialog-scrollable .modal-body {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
}
</style>
@endpush

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Products List</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Table</a></li>
                <li class="breadcrumb-item active" aria-current="page">Products</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}

    <div class="row row-sm">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-bottom"
                     style="margin-bottom: 0; display: flex; justify-content: space-between;">
                    <h3 class="card-title">All Products</h3>
                    <div class="btn-group">
                        <button class="btn btn-danger me-2" id="bulkDelete" style="display: none;">Bulk Delete</button>
                        <a href="{{ route('product.create') }}" class="btn btn-primary">Create New Products</a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered text-nowrap border-bottom w-100" id="datatable">
                            <thead>
                                <tr>
                                    <th class="wd-15p border-bottom-0"><input type="checkbox" id="selectAll"></th>
                                    <th class="wd-15p border-bottom-0">#</th>
                                    <th class="wd-15p border-bottom-0">API ID</th>
                                    <th class="wd-15p border-bottom-0">Title</th>
                                    <th class="wd-15p border-bottom-0">City</th>
                                    <th class="wd-15p border-bottom-0">Thumbnail</th>
                                    <th class="wd-15p border-bottom-0">Provider</th>
                                    <th class="wd-15p border-bottom-0">Booking Type</th>
                                    <th class="wd-15p border-bottom-0">Status</th>
                                    <th class="wd-15p border-bottom-0">Price</th>
                                    <th class="wd-15p border-bottom-0">Is Top</th>
                                    <th class="wd-15p border-bottom-0">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- dynamic data --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Live Raw Activity & Timeslots Modal --}}
    @include('backend.layouts.tempProduct.partials.globaltix_raw_modal')

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {

            $.ajaxSetup({
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                }
            });

            if (!$.fn.DataTable.isDataTable('#datatable')) {
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
                        url: "{{ route('product.index') }}",
                        type: "GET",
                    },

                    columns: [{
                            data: 'checkbox',
                            name: 'checkbox',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'product_api_id',
                            name: 'product_api_id',
                            orderable: true,
                            searchable: true
                        },
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
                        {
                            data: 'city',
                            name: 'city',
                            orderable: true,
                            searchable: true
                        },
                        {
                            data: 'thumbnail',
                            name: 'thumbnail',
                            orderable: true,
                            searchable: true
                        },
                        {
                            data: 'provider',
                            name: 'provider',
                            orderable: true,
                            searchable: true
                        },
                        {
                            data: 'bookingType',
                            name: 'bookingType',
                            orderable: true,
                            searchable: true
                        },
                        {
                            data: 'status',
                            name: 'status',
                            orderable: true,
                            searchable: true
                        },
                        {
                            data: 'price',
                            name: 'price',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'is_top',
                            name: 'is_top',
                            orderable: true,
                            searchable: true
                        },
                        {
                            data: 'action',
                            name: 'action',
                            orderable: false,
                            searchable: false
                        },
                    ],
                    drawCallback: function() {
                        toggleBulkDeleteButton();
                        $('#selectAll').prop('checked', false);
                    }
                });

                // Select All Checkbox
                $('#selectAll').on('change', function() {
                    $('.row-checkbox').prop('checked', this.checked);
                    toggleBulkDeleteButton();
                });

                // Row Checkbox Change
                $(document).on('change', '.row-checkbox', function() {
                    let total = $('.row-checkbox').length;
                    let checked = $('.row-checkbox:checked').length;
                    $('#selectAll').prop('checked', total === checked);
                    toggleBulkDeleteButton();
                });

                function toggleBulkDeleteButton() {
                    if ($('.row-checkbox:checked').length > 0) {
                        $('#bulkDelete').fadeIn();
                    } else {
                        $('#bulkDelete').fadeOut();
                    }
                }

                // Bulk Delete Action
                $('#bulkDelete').on('click', function() {
                    let ids = [];
                    $('.row-checkbox:checked').each(function() {
                        ids.push($(this).val());
                    });

                    if (ids.length > 0) {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: 'You want to delete ' + ids.length + ' selected product(s)?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, delete selected!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $.ajax({
                                    url: "{{ route('product.bulkDelete') }}",
                                    type: "DELETE",
                                    contentType: "application/json",
                                    headers: {
                                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                                    },
                                    data: JSON.stringify({ ids: ids }),
                                    success: function(resp) {
                                        if (resp.success) {
                                            toastr.success(resp.message);
                                            dTable.ajax.reload();
                                            $('#selectAll').prop('checked', false);
                                            $('#bulkDelete').fadeOut();
                                        } else {
                                            toastr.error(resp.message);
                                        }
                                    },
                                    error: function(xhr) {
                                        let msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Bulk delete failed. Please try again.';
                                        toastr.error(msg);
                                    }
                                });
                            }
                        });
                    }
                });
            }
        });

        // Top Product Toggle
        $(document).on('change', '.toggle-top', function() {
            let status = $(this).prop('checked') ? 1 : 0;
            let id = $(this).data('id');
            let url = "{{ route('product.toggleTop', ':id') }}".replace(':id', id);

            $.ajax({
                type: "POST",
                url: url,
                data: {
                    '_token': '{{ csrf_token() }}',
                    'is_top': status
                },
                success: function(resp) {
                    if (resp.success) {
                        toastr.success(resp.message);
                    } else {
                        toastr.error(resp.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message || 'Failed to update status.');
                    $('#datatable').DataTable().ajax.reload();
                }
            });
        });

        // delete Confirm
        function showDeleteConfirm(id) {
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
        }

        // Delete Button
        function deleteItem(id) {
            let url = "{{ route('product.destroy', ':id') }}".replace(':id', id);

            $.ajax({
                type: "DELETE",
                url: url,
                success: function(resp) {
                    $('#datatable').DataTable().ajax.reload();

                    if (resp.success) {
                        toastr.success(resp.message);
                    } else {
                        toastr.error(resp.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message || 'An error occurred. Please try again.');
                }
            });
        }
    </script>
    @include('backend.layouts.product.partials.globaltix_raw_scripts')
@endpush
