@extends('backend.app')

@section('title', 'Add Product')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Add Product</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('product.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group mb-3">
                    <label for="stores_id" class="form-label">Select Store:</label>
                    <select name="stores_id" id="stores_id" class="form-control" required>
                        <option value="">-- Choose Store --</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                    @error('stores_id')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>


                <div class="form-group">
                    <label for="name">Name</label>
                    <input name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="price">Price</label>
                    <input name="price" id="price" type="number" class="form-control @error('price') is-invalid @enderror"
                        value="{{ old('price') }}">
                    @error('price')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="tags">Tags (comma separated)</label>
                    <input name="tags" id="tags" class="form-control"
                        value="{{ old('tags', isset($product) && is_array($product->tags) ? implode(',', $product->tags) : '') }}"
                        placeholder="tag1, tag2, tag3">
                    @error('tags')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>


                <div class="form-group">
                    <label for="photo">Photo</label>
                    <input type="file" name="photo" id="photo" class="form-control">
                    @error('photo')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="offer_type">Offer Type</label>
                    <select name="offer_type" id="offer_type" class="form-control">
                        <option value="">-- Select Offer Type --</option>
                        <option value="50% Off" {{ old('offer_type') == '50% Off' ? 'selected' : '' }}>50% Off
                        </option>
                        <option value="2 In 1" {{ old('offer_type') == '2 In 1' ? 'selected' : '' }}>2 In 1</option>
                        <option value="25% Total Off" {{ old('offer_type') == '25% Total Off' ? 'selected' : '' }}>
                            25% Total Off</option>
                    </select>
                    @error('offer_type')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="offer_des">Offer Description</label>
                    <textarea name="offer_des" id="offer_des" class="form-control">{{ old('offer_des') }}</textarea>
                    @error('offer_des')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="category-wrapper">Categories</label>
                    <div id="category-wrapper">
                        <div class="d-flex mb-2">
                            <input type="text" name="categories[]" class="form-control" placeholder="Enter category">
                            <button type="button" class="btn btn-success ms-2 add-category">+</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('product.index') }}" class="btn btn-danger">Cancel</a>
            </form>
        </div>
    </div>

    <script>
        document.querySelector('.add-category').addEventListener('click', function() {
            const wrapper = document.getElementById('category-wrapper');
            const div = document.createElement('div');
            div.classList.add('d-flex', 'mb-2');
            div.innerHTML = `
        <input type="text" name="categories[]" class="form-control" placeholder="Enter category">
        <button type="button" class="btn btn-danger ms-2 remove-category">-</button>
    `;
            wrapper.appendChild(div);
            div.querySelector('.remove-category').addEventListener('click', function() {
                div.remove();
            });
        });
    </script>
@endsection
