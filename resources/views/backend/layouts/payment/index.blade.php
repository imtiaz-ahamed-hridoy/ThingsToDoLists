@extends('backend.app')

@section('title', 'Payment List')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.4/css/dataTables.dataTables.min.css">
@endpush

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Payment List</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Table</a></li>
                <li class="breadcrumb-item active" aria-current="page">Payment</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}

    <form id="cancelForm" method="POST" style="display:none;">
        @csrf
    </form>


    <div class="row row-sm">
        <div class="col-lg-12">
            <div class="card">
                @if(Auth::user()->role==='Admin')
                <div class="card-header border-bottom"
                    style="margin-bottom: 0; display: flex; justify-content: space-between;">
                    <h3 class="card-title">Create New Payment</h3>
                    <a href="{{route('payment.create')}}" class="btn btn-primary">Create New Payment</a>
                </div>
                @endif

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered text-nowrap border-bottom w-100" id="datatable">
                            <thead>
                                <tr>
                                    <th class="wd-15p border-bottom-0">#</th>
                                    <th class="wd-15p border-bottom-0">User</th>
                                    <th class="wd-15p border-bottom-0">Plan Name</th>
                                    <th class="wd-15p border-bottom-0">Session Id </th>
                                    <th class="wd-15p border-bottom-0">Price Id</th>
                                    <th class="wd-15p border-bottom-0">Start Date</th>
                                    <th class="wd-15p border-bottom-0">Trial End Date</th>
                                    <th class="wd-15p border-bottom-0">End Date</th>
                                    <th class="wd-15p border-bottom-0">Status</th>
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
@endsection
 
@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#datatable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('payment.index') }}",
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'user',
                        name: 'user',
                        orderable: true,
                        searchable: true
                    },
                    {
                        data: 'plan',
                        name: 'plan',
                        orderable: true,
                        searchable: true
                    },
                    {
                        data: 'stripe_id',
                        name: 'stripe_id',
                        orderable: true,
                        searchable: true
                    },
                    {
                        data: 'stripe_price',
                        name: 'stripe_price',
                        orderable: true,
                        searchable: true
                    },
                    {
                        data: 'created_at',
                        name: 'created_at',
                        orderable: true,
                        searchable: true
                    },
                    {
                        data: 'trial_ends_at',
                        name: 'trial_ends_at',
                        orderable: true,
                        searchable: true
                    },
                    {
                        data: 'ends_at',
                        name: 'ends_at',
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
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    },
                ],
            });

            // Custom search input
            $('#customSearch').on('keyup', function() {
                table.search(this.value).draw();
            });
        });

        // delete Confirm
        function showDeleteConfirm(id) {
            event.preventDefault();
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
            let url = '{{ route('payment.destroy', ':id') }}';
            let csrfToken = '{{ csrf_token() }}';
            $.ajax({
                type: "DELETE",
                url: url.replace(':id', id),
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                success: function(resp) {
                    $('#datatable').DataTable().ajax.reload();
                    if (resp['t-success']) {
                        toastr.success(resp.message);
                    } else {
                        toastr.error(resp.message);
                    }
                },
                error: function(error) {
                    toastr.error('An error occurred. Please try again.');
                }
            });
        }
        function cancelSubscription(id) {
            const form = document.getElementById('cancelForm');
            form.action = `/admin/payment/cancel/${id}`; // Or use route()
            form.submit();
        }

        
    </script>
@endpush
