@extends('backend.app')

@section('title', 'Availability Slots')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Availability Slot List</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Table</a></li>
                <li class="breadcrumb-item active" aria-current="page">Availability</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}

    <div class="row row-sm">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-bottom d-flex justify-content-between">
                    <h3 class="card-title">Availability Slots</h3>
                    <div class="btn-group">
                        <button class="btn btn-danger me-2" id="bulkDelete" style="display: none;">Bulk Delete</button>
                        <a href="{{ route('availability.create') }}" class="btn btn-primary">Create New Availability</a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered text-nowrap border-bottom w-100" id="datatable">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Provider</th>
                                    <th>Date</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Capacity</th>
                                    <th>Participants</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $.ajaxSetup({
        headers: { "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content") }
    });

    let dTable = $('#datatable').DataTable({
        order: [],
        lengthMenu: [[10,25,50,100,-1],[10,25,50,100,"All"]],
        processing: true,
        serverSide: true,
        responsive: true,
        language: {
            processing: `<div class="text-center">
                <div class="spinner-border text-primary" style="width:3rem;height:3rem;"></div>
            </div>`
        },
        pagingType: "full_numbers",
        dom: "<'row justify-content-between table-topbar'<'col-md-2 px-0'l><'col-md-2 px-0'f>>tipr",
        ajax: "{{ route('availability.index') }}",
        columns: [
            { data: 'checkbox', name: 'checkbox', orderable:false, searchable:false },
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable:false, searchable:false },
            { data: 'product', name: 'product.title', orderable:true, searchable:true },
            { data: 'provider', name: 'provider' },
            { data: 'available_date', name: 'available_date' },
            { data: 'start_time', name: 'start_time' },
            { data: 'end_time', name: 'end_time' },
            { data: 'capacity', name: 'capacity', orderable:false, searchable:false },
            { data: 'max_participants', name: 'max_participants', orderable:false, searchable:false },
            { data: 'status', name: 'status', orderable:false, searchable:false },
            { data: 'action', name: 'action', orderable:false, searchable:false }
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
                text: 'You want to delete ' + ids.length + ' selected record(s)?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete selected!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('availability.bulkDelete') }}",
                        type: "DELETE",
                        data: {
                            ids: ids
                        },
                        success: function(resp) {
                            if (resp['t-success']) {
                                toastr.success(resp.message);
                                dTable.ajax.reload();
                                $('#selectAll').prop('checked', false);
                            } else {
                                toastr.error(resp.message);
                            }
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON.message || 'Bulk delete failed.');
                        }
                    });
                }
            });
        }
    });

    // DELETE CONFIRM
    window.showDeleteConfirm = function(id) {
        Swal.fire({
            title: 'Are you sure you want to delete this record?',
            text: 'If you delete this, it will be gone forever.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteItem(id);
            }
        });
    };

    function deleteItem(id) {
        let url = "{{ route('availability.destroy', ':id') }}";
        $.ajax({
            type: "DELETE",
            url: url.replace(':id', id),
            success: function(resp) {
                if(resp['t-success']){
                    toastr.success(resp.message);
                    dTable.ajax.reload(null, false); // reload table without resetting pagination
                } else {
                    toastr.error(resp.message);
                }
            },
            error: function(xhr){
                toastr.error(xhr.responseJSON.message || 'An error occurred. Please try again.');
            }
        });
    }
});

</script>
@endpush
