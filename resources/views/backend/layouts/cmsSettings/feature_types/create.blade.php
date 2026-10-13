@extends('backend.app')

@section('title', 'Create Feature Type')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Create Feature Type</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('cmsSettings.featureTypes.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>

            <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug') }}" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control">{{ old('description') }}</textarea>
            </div>

            <div class="form-group">
                <label>Products (select multiple)</label>
                <select name="product_ids[]" class="form-control" multiple>
                    @foreach($products as $p)
                    <option value="{{ $p->id }}">{{ $p->title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <button class="btn btn-primary">Create</button>
        </form>
    </div>
</div>
@endsection