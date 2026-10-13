@extends('backend.app')
@section('title', 'Destination Highlights')
@push('styles')
    <link href="{{ asset('assets/backend/css/datatable.css') }}" rel="stylesheet">
@endpush
@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Destination Highlights List</h5>
                    <a href="{{ route('destination-highlights.create') }}" class="btn btn-primary btn-sm">
                        <i class="fa fa-plus"></i> Add New Highlight
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="highlights-table">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Destination</th>
                                    <th>Products Count</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(function () {
            $('#highlights-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('destination-highlights.index') }}',
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { 
                        data: 'image', 
                        name: 'image', 
                        orderable: false, 
                        searchable: false,
                        render: function(data, type, full, meta) {
                            if (data) {
                                return `<img src="${data}" alt="Image" style="height: 40px; width: 60px; object-fit: cover; border-radius: 4px;">`;
                            }
                            return '<span class="text-muted">No Image</span>';
                        }
                    },
                    { data: 'name', name: 'name' },
                    { data: 'destination', name: 'destination' },
                    { data: 'products_count', name: 'products_count', searchable: false },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ]
            });
        });

        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/admin/destination-highlights/${id}`,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire('Deleted!', response.message, 'success');
                                $('#highlights-table').DataTable().ajax.reload();
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        },
                        error: function () {
                            Swal.fire('Error!', 'Something went wrong.', 'error');
                        }
                    });
                }
            });
        }
    </script>
@endpush
