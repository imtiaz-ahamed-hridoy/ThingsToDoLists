{{-- =========================
       Vendor Details
    ========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0 text-primary fw-semibold">Vendor Details</h4>
        <div>
            <input type="hidden" name="use_existing_vendor" value="0">
            <input type="checkbox" name="use_existing_vendor" id="use_existing_vendor" 
                   value="1" {{ old('use_existing_vendor') ? 'checked' : '' }}>
            <label for="use_existing_vendor" class="mb-0">Use existing from Product</label>
        </div>
    </div>
    <div class="card-body">
        <div id="vendor_card_body" style="{{ old('use_existing_vendor') ? 'display:none;' : '' }}">
            <div class="mb-3">
                <label for="vendor_name" class="form-label">Vendor Name</label>
                <input type="text" name="vendor[name]" id="vendor_name" class="form-control" 
                       value="{{ old('vendor.name') }}" placeholder="Enter vendor name">
                @error('vendor.name')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="mb-3">
                @php
                    $vendorCurrencies = ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'AED', 'EGP', 'SAR'];
                    $selectedVendorCurrency = old('vendor.currency', 'USD');
                    if ($selectedVendorCurrency && ! in_array($selectedVendorCurrency, $vendorCurrencies, true)) {
                        $vendorCurrencies[] = $selectedVendorCurrency;
                    }
                @endphp
                <label for="vendor_currency" class="form-label">Currency</label>
                <select name="vendor[currency]" id="vendor_currency" class="form-select">
                    @foreach($vendorCurrencies as $currency)
                        <option value="{{ $currency }}" {{ $selectedVendorCurrency === $currency ? 'selected' : '' }}>{{ $currency }}</option>
                    @endforeach
                </select>
                <small class="text-muted d-block mt-1">Use the supplier settlement currency code.</small>
                @error('vendor.currency')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="mb-3">
                <label for="vendor_rating" class="form-label">Rating</label>
                <input type="number" name="vendor[rating]" id="vendor_rating" class="form-control" 
                       value="{{ old('vendor.rating') }}" min="0" max="5" step="0.1">
                @error('vendor.rating')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="mb-3">
                <label for="vendor_reviews" class="form-label">Total Reviews</label>
                <input type="number" name="vendor[reviews]" id="vendor_reviews" class="form-control" 
                       value="{{ old('vendor.reviews') }}" min="0">
                @error('vendor.reviews')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="mb-3">
                <label for="vendor_link" class="form-label">Vendor Website / Link</label>
                <input type="url" name="vendor[link]" id="vendor_link" class="form-control" 
                       value="{{ old('vendor.link') }}" placeholder="https://example.com">
                @error('vendor.link')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>
        
        <div id="existing_vendor_select" style="{{ old('use_existing_vendor') ? '' : 'display:none;' }}">
            <div class="mb-3">
                <label for="vendor_id" class="form-label">Select Existing Vendor</label>
                <select name="vendor_id" id="vendor_id" class="form-control">
                    <option value="">Select Vendor</option>
                    @foreach($vendors as $id => $name)
                        <option value="{{ $id }}" {{ old('vendor_id') == $id ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
                @error('vendor_id')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('use_existing_vendor').addEventListener('change', function() {
    const isChecked = this.checked;
    document.getElementById('vendor_card_body').style.display = isChecked ? 'none' : '';
    document.getElementById('existing_vendor_select').style.display = isChecked ? '' : 'none';
});
</script>
