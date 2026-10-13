{{-- =========================
     Barcode Type
========================== --}}
<div class="card mb-4">
    <div class="card-header">
        <h4 class="mb-0 text-primary fw-semibold">Barcode Type</h4>
    </div>

    <div class="card-body">
        <div class="mb-3">
            <label for="barcode_type" class="form-label">Barcode Type</label>
            <input
                type="text"
                name="barcode_type"
                id="barcode_type"
                class="form-control"
                value="{{ old('barcode_type', $product->barcode_type ?? '') }}"
                placeholder="e.g. QR_CODE, EAN_13, PDF417"
            >
            @error('barcode_type')<span class="text-danger">{{ $message }}</span>@enderror
        </div>
    </div>
</div>
