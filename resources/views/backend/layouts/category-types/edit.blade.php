@extends('backend.app')

@section('title', 'Edit Category Type')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Category Type</h1>
        </div>
    </div>

    <div class="card box-shadow-0">
        <div class="card-body">
            <form action="{{ route('category-type.update', $type->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $type->name) }}" required>
                    @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug', $type->slug) }}" required>
                    @error('slug')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="1" {{ old('status', (string) $type->status) == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('status', (string) $type->status) == '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('category-type.index') }}" class="btn btn-danger">Cancel</a>
            </form>
        </div>
    </div>
@endsection
