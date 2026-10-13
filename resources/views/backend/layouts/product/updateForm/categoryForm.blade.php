{{-- =========================
       Category Details - Update
========================= --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0 text-primary fw-semibold">Category Details</h4>
        <div>
            <input type="hidden" name="use_existing_category" value="0">
            <input type="checkbox" name="use_existing_category" id="use_existing_category" 
                   value="1" {{ old('use_existing_category', isset($product->category) ? 1 : 0) ? 'checked' : '' }}>
            <label for="use_existing_category" class="mb-0">Use existing from Product</label>
        </div>
    </div>
    <div class="card-body">
        <div id="category_card_body" style="{{ old('use_existing_category', isset($product->category) ? 1 : 0) ? 'display:none;' : '' }}">
            <div class="mb-3">
                <label for="category_name" class="form-label">Category Name</label>
                <input type="text" name="category[name]" id="category_name" class="form-control" 
                       value="{{ old('category.name', $product->category->name ?? '') }}" placeholder="Enter category name">
                @error('category.name')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="mb-3">
                <label for="category_slug" class="form-label">Category Slug</label>
                <input type="text" name="category[slug]" id="category_slug" class="form-control" 
                       value="{{ old('category.slug', $product->category->slug ?? '') }}" placeholder="unique-category-slug">
                @error('category.slug')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <input type="hidden" name="category[type]" value="product">

        </div>

        <div id="existing_category_select" style="{{ old('use_existing_category', isset($product->category) ? 1 : 0  ) ? '' : 'display:none;' }}">
            <div class="mb-3">
                <label for="category_id" class="form-label">Select Existing Category</label>
                <select name="category_id" id="category_id" class="form-control">
                    <option value="">Select Category</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}" 
                            {{ old('category_id', $product->category->id ?? '') == $id ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('use_existing_category').addEventListener('change', function() {
    const isChecked = this.checked;
    document.getElementById('category_card_body').style.display = isChecked ? 'none' : '';
    document.getElementById('existing_category_select').style.display = isChecked ? '' : 'none';
});
</script>
@endpush
