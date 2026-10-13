{{-- =========================
     PRODUCT IMAGES UPLOAD - Edit Form
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Product Images</h4>
        <small class="text-muted">Add or update multiple images. Each image can have variants: original, large, preview. Max 10MB per image.</small>
    </div>
    <div class="card-body">
        <div id="images_container">
            @foreach($product->images as $index => $image)
            <div class="image-row mb-3 border p-2 rounded position-relative" data-existing-id="{{ $image->id }}">
                <button type="button" class="btn btn-danger btn-sm remove-image-row position-absolute" style="top:5px; right:5px;">
                    &times;
                </button>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Original</label>
                        <input type="file" name="images[{{ $index }}][original]" class="form-control image-input" accept="image/*">
                        @if(!empty($image->original))
                        <img src="{{ $image->original }}" class="image-preview mt-1">
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Large</label>
                        <input type="file" name="images[{{ $index }}][large]" class="form-control image-input" accept="image/*">
                        @if(!empty($image->large))
                        <img src="{{ $image->large }}" class="image-preview mt-1">
                        @endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Preview</label>
                        <input type="file" name="images[{{ $index }}][preview]" class="form-control image-input" accept="image/*">
                        @if(!empty($image->preview))
                        <img src="{{ $image->preview }}" class="image-preview mt-1">
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Store IDs of images to delete -->
        <input type="hidden" name="deleted_images" id="deleted_images" value="">

        <button type="button" class="btn btn-secondary mt-2" id="add_image_row">
            <i class="fas fa-plus me-1"></i> Add Image
        </button>
    </div>
</div>

<template id="image_row_template">
    <div class="image-row mb-3 border p-2 rounded position-relative">
        <button type="button" class="btn btn-danger btn-sm remove-image-row position-absolute" style="top:5px; right:5px;">
            &times;
        </button>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Original <span class="text-danger">*</span></label>
                <input type="file" name="images[__INDEX__][original]" class="form-control image-input" accept="image/*" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Large</label>
                <input type="file" name="images[__INDEX__][large]" class="form-control image-input" accept="image/*">
            </div>
            <div class="col-md-4">
                <label class="form-label">Preview</label>
                <input type="file" name="images[__INDEX__][preview]" class="form-control image-input" accept="image/*">
            </div>
        </div>
    </div>
</template>

<style>
.image-preview { 
    width: 120px; 
    height: 120px; 
    object-fit: cover; 
    border-radius: 8px; 
    border: 2px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let imageIndex = {{ $product->images->count() }};
    const addRowBtn = document.getElementById('add_image_row');
    const container = document.getElementById('images_container');
    const template = document.getElementById('image_row_template').innerHTML;
    const deletedInput = document.getElementById('deleted_images');

    addRowBtn.addEventListener('click', () => {
        const html = template.replace(/__INDEX__/g, imageIndex);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        container.appendChild(wrapper.firstElementChild);
        imageIndex++;
    });

    // Remove image row
    container.addEventListener('click', function(e){
        if(e.target.matches('.remove-image-row')){
            const row = e.target.closest('.image-row');

            // If it’s an existing image, store its ID to delete
            const existingId = row.dataset.existingId;
            if(existingId){
                let deleted = deletedInput.value ? deletedInput.value.split(',') : [];
                deleted.push(existingId);
                deletedInput.value = deleted.join(',');
            }

            row.remove();
        }
    });

    // Show preview for new files
    container.addEventListener('change', function(e){
        if(!e.target.matches('.image-input')) return;
        const file = e.target.files[0];
        if(!file) return;

        const maxSize = 10 * 1024 * 1024; // 10MB
        if(file.size > maxSize) {
            alert('File size must not exceed 10MB');
            e.target.value = '';
            return;
        }
        if(!file.type.startsWith('image/')) {
            alert('Please select a valid image file');
            e.target.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(ev) {
            let existingImg = e.target.parentNode.querySelector('img.image-preview');
            if(!existingImg){
                const img = document.createElement('img');
                img.src = ev.target.result;
                img.classList.add('image-preview', 'mt-1');
                e.target.parentNode.appendChild(img);
            } else {
                existingImg.src = ev.target.result;
            }
        }
        reader.readAsDataURL(file);
    });
});
</script>
