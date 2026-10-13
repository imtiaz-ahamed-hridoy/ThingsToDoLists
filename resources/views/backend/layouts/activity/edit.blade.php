@extends('backend.app')

@section('title', 'Edit Product')

@section('content')
 <style>
        /* custom form style */
        .form-control {
            border-radius: 6px;
            color: #333;
            transition: 0.3s;
        }

        .form-control:focus {
            background-color: #ffffff;
        }
    </style>
    <div class="page-header">
        <h1 class="page-title">Edit Product</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('product.update', $product->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="form-group mb-3">
                    <label for="stores_id">Store</label>
                    <select name="stores_id" class="form-control" required>
                        <option value="">-- Select Store --</option>
                        @foreach (App\Models\Store::all() as $store)
                            <option value="{{ $store->id }}"
                                {{ old('stores_id', $product->stores_id) == $store->id ? 'selected' : '' }}>
                                {{ $store->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('stores_id')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="name">Name</label>
                    <input name="name" id="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                    @error('name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="tags">Tags (comma separated)</label>
                    <input type="text" name="tags" id="tags" class="form-control"
                        value="{{ old('tags', isset($product->tags) && is_array($product->tags) ? implode(',', $product->tags) : $product->tags ?? '') }}"
                        placeholder="tag1, tag2, tag3">
                    @error('tags')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="photo">Photo</label><br>
                    @if ($product->photo)
                        <img src="{{ asset('storage/' . $product->photo) }}" alt="Photo" width="100"
                            class="mb-2 rounded">
                    @endif
                    <input type="file" name="photo" id="photo" class="form-control">
                    @error('photo')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="price">Price</label>
                    <input name="price" id="price" type="number" step="0.01" class="form-control"
                        value="{{ old('price', $product->price) }}">
                    @error('price')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label for="category-container">Categories</label>
                    <div id="category-container">
                        @foreach ($product->categories as $cat)
                            <div class="d-flex mb-2">
                                <input type="text" name="categories[]" class="form-control"
                                    value="{{ old('categories.' . $loop->index, $cat->category) }}">
                                <button type="button" class="btn btn-danger ms-2 remove-category">−</button>
                            </div>
                        @endforeach
                        @error('categories[]')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                    <button type="button" id="add-category" class="btn btn-sm btn-success mt-2">+ Add Category</button>
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

                <div class="form-group mb-3">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="Active" {{ old('status', $product->status) == 'Active' ? 'selected' : '' }}>Active
                        </option>
                        <option value="Inactive" {{ old('status', $product->status) == 'Inactive' ? 'selected' : '' }}>
                            Inactive</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Update Product</button>
                <a href="{{ route('product.index') }}" class="btn btn-danger me-2">Cancel</a>
            </form>

            <script>
                document.getElementById('add-category').addEventListener('click', function() {
                    const container = document.getElementById('category-container');
                    const div = document.createElement('div');
                    div.classList.add('d-flex', 'mb-2');
                    div.innerHTML = `
        <input type="text" name="categories[]" class="form-control" placeholder="Enter category">
        <button type="button" class="btn btn-danger ms-2 remove-category">−</button>
    `;
                    container.appendChild(div);
                });

                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-category')) {
                        e.target.parentElement.remove();
                    }
                });
            </script>
        @endsection
