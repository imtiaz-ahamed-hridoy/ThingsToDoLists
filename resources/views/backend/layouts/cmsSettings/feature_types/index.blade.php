@extends('backend.app')

@section('title', 'Feature Types')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Feature Types</h3>
        <div class="card-options">
            <a href="{{ route('cmsSettings.featureTypes.create') }}" class="btn btn-primary">Create Feature Type</a>
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($types as $t)
                <tr>
                    <td>{{ $t->id }}</td>
                    <td>{{ $t->name }}</td>
                    <td>{{ $t->slug }}</td>
                    <td>{{ $t->products()->count() }}</td>
                    <td>{{ $t->status ? 'Active' : 'Inactive' }}</td>
                    <td>
                        <a href="{{ route('cmsSettings.featureTypes.edit', $t->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                        <form action="{{ route('cmsSettings.featureTypes.destroy', $t->id) }}" method="POST" style="display:inline-block;">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection