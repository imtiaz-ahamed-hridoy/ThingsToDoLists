@extends('backend.app')

@section('title', 'Category Types')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Category Types</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <a href="{{ route('category-type.create') }}" class="btn btn-primary">Create Category Type</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Categories</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($types as $type)
                            <tr>
                                <td>{{ $type->name }}</td>
                                <td><code>{{ $type->slug }}</code></td>
                                <td>
                                    @if ((int) $type->status === 1)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ (int) $type->categories_count }}</td>
                                <td>
                                    <a href="{{ route('category-type.edit', $type->id) }}" class="btn btn-sm btn-primary">Edit</a>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteCategoryType({{ $type->id }})">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No category types found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function deleteCategoryType(id) {
            if (!confirm('Delete this category type?')) {
                return;
            }

            fetch("{{ route('category-type.destroy', ':id') }}".replace(':id', id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            }).then(async (response) => {
                const payload = await response.json();
                alert(payload.message || 'Request finished.');
                if (response.ok) {
                    window.location.reload();
                }
            });
        }
    </script>
@endsection
