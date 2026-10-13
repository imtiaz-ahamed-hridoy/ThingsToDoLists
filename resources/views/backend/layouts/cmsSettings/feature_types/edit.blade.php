@extends('backend.app')

@section('title', 'Edit Feature Type')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Edit Feature Type</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('cmsSettings.featureTypes.update', $type->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $type->name) }}" required>
            </div>

            <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug', $type->slug) }}" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control">{{ old('description', $type->description) }}</textarea>
            </div>

            <div class="form-group">
                <label>Products (select multiple)</label>
                <select name="product_ids[]" class="form-control" multiple>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}" {{ $type->products->contains($p->id) ? 'selected' : '' }}>{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="1" {{ $type->status ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ !$type->status ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            <button class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
@endsection