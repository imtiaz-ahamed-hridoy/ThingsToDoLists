{{-- =========================
     Product Form
========================== --}}
<div class="card mb-4">
    <div class="card-header">
        <h4 class="mb-0 text-primary fw-semibold">Product Details</h4>
    </div>

    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Provider</label>
                <select name="provider" class="form-select">
                    @foreach(['manual' => 'Manual (Admin Created)', 'supplier' => 'Supplier (API)', 'bokun' => 'Bokun', 'globaltix' => 'GlobalTix'] as $value => $label)
                        <option value="{{ $value }}" {{ old('provider', $product->provider ?? 'manual') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('provider')<span class="text-danger">{{ $message }}</span>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Language</label>
                <input type="text" name="language" class="form-control" value="{{ old('language', $product->language ?? 'en') }}" maxlength="10">
                @error('language')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Product API ID</label>
                <input type="text" name="product_api_id" class="form-control" value="{{ old('product_api_id', $product->product_api_id ?? '') }}">
                @error('product_api_id')<span class="text-danger">{{ $message }}</span>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Reference Code</label>
                <input type="text" name="reference_code" class="form-control" value="{{ old('reference_code', $product->reference_code ?? '') }}">
                @error('reference_code')<span class="text-danger">{{ $message }}</span>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label">Product Category</label>
                <input type="text" name="product_category" class="form-control" value="{{ old('product_category', $product->product_category ?? '') }}">
                @error('product_category')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- Title --}}
        <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $product->title ?? '') }}" required>
            @error('title')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Description --}}
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $product->description ?? '') }}</textarea>
            @error('description')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Short Introduction</label>
            <input type="text" name="short_introduction" class="form-control" value="{{ old('short_introduction', $product->short_introduction ?? '') }}" maxlength="255">
            @error('short_introduction')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Full Description</label>
            <textarea name="full_description" class="form-control" rows="5">{{ old('full_description', $product->full_description ?? '') }}</textarea>
            @error('full_description')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label">Highlights Summary</label>
            <textarea name="highlights_summary" class="form-control" rows="3">{{ old('highlights_summary', $product->highlights_summary ?? '') }}</textarea>
            @error('highlights_summary')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Thumbnail --}}
        <div class="mb-3">
            <label for="thumbnail" class="form-label">Thumbnail Image:</label>
            <input type="file" class="dropify @error('thumbnail') is-invalid @enderror"
                name="thumbnail" placeholder="thumbnail image" id="thumbnail">
            @error('thumbnail')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

        {{-- Duration --}}
        <div class="mb-3">
            <label class="form-label">Duration</label>
            <input type="text" name="duration" class="form-control" value="{{ old('duration', $product->duration ?? '') }}">
            @error('duration')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <label class="form-label">Duration Type</label>
                <input type="text" name="durationType" class="form-control" value="{{ old('durationType', $product->durationType ?? '') }}">
                @error('durationType')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Minutes</label>
                <input type="number" name="durationMinutes" class="form-control" value="{{ old('durationMinutes', $product->durationMinutes ?? '') }}" min="0">
                @error('durationMinutes')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Hours</label>
                <input type="number" name="durationHours" class="form-control" value="{{ old('durationHours', $product->durationHours ?? '') }}" min="0">
                @error('durationHours')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Duration Text</label>
                <input type="text" name="durationText" class="form-control" value="{{ old('durationText', $product->durationText ?? '') }}">
                @error('durationText')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- Difficulty --}}
        <div class="mb-3">
            <label class="form-label">Difficulty</label>
            <select name="difficulty" class="form-select">
                @php 
                    $opts = ['EASY','MEDIUM','HARD'];
                    if(isset($product->difficulty) && !in_array($product->difficulty, $opts)) { $opts[] = $product->difficulty; }
                @endphp
                @foreach($opts as $level)
                    <option value="{{ $level }}" {{ old('difficulty', $product->difficulty ?? 'EASY') === $level ? 'selected' : '' }}>{{ $level }}</option>
                @endforeach
            </select>
            @error('difficulty')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Pickup Service --}}
        <div class="mb-3">
            <label class="form-label">Pickup Service</label>
            <input type="text" name="pickup_service" class="form-control" value="{{ old('pickup_service', $product->pickup_service ?? '') }}">
            @error('pickup_service')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Private Tour --}}
        <div class="mb-3">
            <label class="form-label">Private Tour</label>
            <input type="text" name="private_tour" class="form-control" value="{{ old('private_tour', $product->private_tour ?? '') }}">
            @error('private_tour')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label">Guide Type</label>
                <input type="text" name="guide_type" class="form-control" value="{{ old('guide_type', $product->guide_type ?? '') }}">
                @error('guide_type')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Max Group Size</label>
                <input type="number" name="max_group_size" class="form-control" value="{{ old('max_group_size', $product->max_group_size ?? '') }}" min="0">
                @error('max_group_size')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- Published --}}
        <div class="form-check mb-3">
            <input type="hidden" name="published" value="0">
            <input type="checkbox" name="published" value="1" class="form-check-input" {{ old('published', $product->published ?? true) ? 'checked' : '' }}>
            <label class="form-check-label">Published (visible to users)</label>
            @error('published')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Slug --}}
        <div class="mb-3">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-control" value="{{ old('slug', $product->slug ?? '') }}">
            @error('slug')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Review Rating --}}
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label">Review Rating</label>
                <input type="number" name="reviewRating" class="form-control" value="{{ old('reviewRating', $product->reviewRating ?? '') }}" min="0" max="5" step="0.1">
                @error('reviewRating')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Review Count</label>
                <input type="number" name="reviewCount" class="form-control" value="{{ old('reviewCount', $product->reviewCount ?? '') }}" min="0">
                @error('reviewCount')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- Activity Type --}}
        <div class="mb-3">
            <label class="form-label">Activity Type</label>
            <select name="activityType" class="form-select">
                @php 
                    $opts = ['TOUR','ACTIVITY','DAY_TOUR_OR_ACTIVITY'];
                    if(isset($product->activityType) && !in_array($product->activityType, $opts)) { $opts[] = $product->activityType; }
                @endphp
                @foreach($opts as $type)
                    <option value="{{ $type }}" {{ old('activityType', $product->activityType ?? 'ACTIVITY') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
            @error('activityType')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Booking Type --}}
        <div class="mb-3">
            <label class="form-label">Booking Type</label>
            <select name="bookingType" class="form-select">
                @php 
                    $opts = ['PRIVATE','SHARED','DATE_AND_TIME'];
                    if(isset($product->bookingType) && !in_array($product->bookingType, $opts)) { $opts[] = $product->bookingType; }
                @endphp
                @foreach($opts as $type)
                    <option value="{{ $type }}" {{ old('bookingType', $product->bookingType ?? 'SHARED') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
            @error('bookingType')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Cutoff Type & Reference --}}
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label">Cutoff Type</label>
                <select name="cutoffType" class="form-select">
                    @php 
                        $opts = ['FIXED','RELATIVE','RELATIVE_TO_START_TIME'];
                        if(isset($product->cutoffType) && !in_array($product->cutoffType, $opts)) { $opts[] = $product->cutoffType; }
                    @endphp
                    @foreach($opts as $type)
                        <option value="{{ $type }}" {{ old('cutoffType', $product->cutoffType ?? 'FIXED') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('cutoffType')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Cutoff Hour</label>
                <input type="number" name="cutoffReferenceHour" class="form-control" value="{{ old('cutoffReferenceHour', $product->cutoffReferenceHour ?? '') }}">
                @error('cutoffReferenceHour')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Cutoff Minute</label>
                <input type="number" name="cutoffReferenceMinute" class="form-control" value="{{ old('cutoffReferenceMinute', $product->cutoffReferenceMinute ?? '') }}">
                @error('cutoffReferenceMinute')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- Capacity --}}
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label">Capacity Type</label>
                <select name="capacityType" class="form-select">
                    @php 
                        $opts = ['FIXED','FLEXIBLE','FREE_SALE'];
                        if(isset($product->capacityType) && !in_array($product->capacityType, $opts)) { $opts[] = $product->capacityType; }
                    @endphp
                    @foreach($opts as $type)
                        <option value="{{ $type }}" {{ old('capacityType', $product->capacityType ?? 'FIXED') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('capacityType')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Pass Capacity</label>
                <input type="number" name="passCapacity" class="form-control" value="{{ old('passCapacity', $product->passCapacity ?? '') }}">
                @error('passCapacity')<span class="text-danger">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- Passes Available --}}
        <div class="mb-3">
            <label class="form-label">Passes Available</label>
            <input type="number" name="passesAvailable" class="form-control" value="{{ old('passesAvailable', $product->passesAvailable ?? '') }}">
            @error('passesAvailable')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Booleans --}}
        <div class="form-check mb-3">
            <input type="hidden" name="allowCustomizedBookings" value="0">
            <input type="checkbox" name="allowCustomizedBookings" value="1" class="form-check-input" {{ old('allowCustomizedBookings', $product->allowCustomizedBookings ?? false) ? 'checked' : '' }}>
            <label class="form-check-label">Allow Customized Bookings</label>
            @error('allowCustomizedBookings')<span class="text-danger">{{ $message }}</span>@enderror
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="passportRequired" value="0">
            <input type="checkbox" name="passportRequired" value="1" class="form-check-input" {{ old('passportRequired', $product->passportRequired ?? false) ? 'checked' : '' }}>
            <label class="form-check-label">Passport Required</label>
            @error('passportRequired')<span class="text-danger">{{ $message }}</span>@enderror
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="is_instant_confirmation" value="0">
            <input type="checkbox" name="is_instant_confirmation" value="1" class="form-check-input" {{ old('is_instant_confirmation', $product->is_instant_confirmation ?? false) ? 'checked' : '' }}>
            <label class="form-check-label">Instant Confirmation</label>
            @error('is_instant_confirmation')<span class="text-danger">{{ $message }}</span>@enderror
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="is_cancellable" value="0">
            <input type="checkbox" name="is_cancellable" value="1" class="form-check-input" {{ old('is_cancellable', $product->is_cancellable ?? false) ? 'checked' : '' }}>
            <label class="form-check-label">Cancellable</label>
            @error('is_cancellable')<span class="text-danger">{{ $message }}</span>@enderror
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="online_reservation" value="0">
            <input type="checkbox" name="online_reservation" value="1" class="form-check-input" {{ old('online_reservation', $product->online_reservation ?? true) ? 'checked' : '' }}>
            <label class="form-check-label">Online Reservation</label>
            @error('online_reservation')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Status --}}
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                @foreach(['draft' => 'Draft', 'active' => 'Active', 'inactive' => 'Inactive'] as $statusKey => $label)
                    <option value="{{ $statusKey }}" {{ old('status', $product->status ?? 'active') === $statusKey ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

    </div>
</div>
