{{-- =========================
     PRODUCT IMAGES UPLOAD - Dynamic Multiple Variants (Create Form)
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Product Images</h4>
        <small class="text-muted">Add multiple images. Each image can have variants: original, large, preview. Max 10MB per image.</small>
    </div>
    <div class="card-body">

        <div id="images_container">
            <div class="image-row mb-3 border p-2 position-relative">
                <button type="button" class="btn btn-danger btn-sm remove-image-row position-absolute" style="top:5px; right:5px;">
                    &times;
                </button>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Original <span class="text-danger">*</span></label>
                        <input type="file" name="images[0][original]" class="dropify image-input" accept="image/*" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Large</label>
                        <input type="file" name="images[0][large]" class="dropify image-input" accept="image/*">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Preview</label>
                        <input type="file" name="images[0][preview]" class="dropify image-input" accept="image/*">
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-secondary mt-2" id="add_image_row">
            <i class="fas fa-plus me-1"></i> Add Image
        </button>
    </div>
</div>

{{-- Hidden template for new row --}}
<template id="image_row_template">
    <div class="image-row mb-3 border p-2 position-relative">
        <button type="button" class="btn btn-danger btn-sm remove-image-row position-absolute" style="top:5px; right:5px;">
            &times;
        </button>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Original <span class="text-danger">*</span></label>
                <input type="file" name="images[__INDEX__][original]" class="dropify image-input" accept="image/*" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Large</label>
                <input type="file" name="images[__INDEX__][large]" class="dropify image-input" accept="image/*">
            </div>
            <div class="col-md-4">
                <label class="form-label">Preview</label>
                <input type="file" name="images[__INDEX__][preview]" class="dropify image-input" accept="image/*">
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
    let imageIndex = 1;
    const addRowBtn = document.getElementById('add_image_row');
    const container = document.getElementById('images_container');
    const template = document.getElementById('image_row_template').innerHTML;

    // Add new image row
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
            e.target.closest('.image-row').remove();
        }
    });

    // Show preview for each selected file
    container.addEventListener('change', function(e){
        if(!e.target.matches('.image-input')) return;
        const file = e.target.files[0];
        if(!file) return;

        const maxSize = 100 * 1024 * 1024; // 100MB
        if(file.size > maxSize) {
            alert('File size must not exceed 100MB');
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
