{{-- =========================
     Product Form - edit
========================== --}}
<div class="card mb-4">
    <div class="card-header">
        <h4 class="mb-0 text-primary fw-semibold">Product Details</h4>
    </div>

    <div class="card-body">
        {{-- Title --}}
        <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $product->title ?? '') }}" >
            @error('title')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Description --}}
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $product->description ?? '') }}</textarea>
            @error('description')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Thumbnail --}}
        <div class="mb-3">
            <label for="thumbnail" class="form-label">Thumbnail Image:</label>
            <input type="file" class="dropify @error('thumbnail') is-invalid @enderror"
                name="thumbnail" id="thumbnail"
                data-default-file="{{ isset($product->thumbnail) ? asset('storage/' . $product->thumbnail) : '' }}">
            @error('thumbnail')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Duration --}}
        <div class="mb-3">
            <label class="form-label">Duration</label>
            <input type="text" name="duration" class="form-control" value="{{ old('duration', $product->duration ?? '') }}">
            @error('duration')<span class="text-danger">{{ $message }}</span>@enderror
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

        {{-- Published --}}
        <div class="form-check mb-3">
            <input type="hidden" name="published" value="0">
            <input type="checkbox" name="published" value="1" class="form-check-input" {{ old('published', $product->published ?? false) ? 'checked' : '' }}>
            <label class="form-check-label">Published</label>
            @error('published')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Slug --}}
        <div class="mb-3">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" class="form-control" value="{{ old('slug', $product->slug ?? '') }}">
            @error('slug')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        {{-- Review Rating --}}
        <div class="mb-3">
            <label class="form-label">Review Rating</label>
            <input type="number" name="reviewRating" class="form-control" value="{{ old('reviewRating', $product->reviewRating ?? '') }}" min="0" max="5">
            @error('reviewRating')<span class="text-danger">{{ $message }}</span>@enderror
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

        {{-- Status --}}
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                @php 
                    $opts = ['ACTIVE','INACTIVE'];
                    if(isset($product->status) && !in_array($product->status, $opts)) { $opts[] = $product->status; }
                @endphp
                @foreach($opts as $status)
                    <option value="{{ $status }}" {{ old('status', $product->status ?? 'INACTIVE') === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
            @error('status')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

    </div>
</div>
