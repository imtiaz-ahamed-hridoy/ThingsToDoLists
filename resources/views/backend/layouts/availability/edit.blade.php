@extends('backend.app')

@section('title', 'Edit Availability Slot')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Availability Slot</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Edit</a></li>
                <li class="breadcrumb-item active" aria-current="page">Availability Slot</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}

    <div class="row">
        <div class="col-lg-12">
            <div class="card box-shadow-0">
                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if($errors->has('duplicate'))
                        <div class="alert alert-danger">{{ $errors->first('duplicate') }}</div>
                    @endif

                    <form method="POST" action="{{ route('availability.update', $availability->id) }}">
                        @csrf
                        @method('PUT')

                        {{-- Product --}}
                        <div class="form-group">
                            <label class="form-label" for="product_id">Product</label>
                            <select name="product_id" id="product_id" class="form-control" required>
                                <option value="">Select Product</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}"
                                        {{ old('product_id', $availability->product_id) == $product->id ? 'selected' : '' }}>
                                        {{ $product->title }}
                                    </option>
                                @endforeach
                            </select>
                            @error('product_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        {{-- Provider --}}
                        <div class="form-group">
                            <label class="form-label" for="provider">Provider</label>
                            <input type="text" name="provider" id="provider" class="form-control"
                                   value="{{ old('provider', $availability->provider) }}">
                            @error('provider') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        {{-- Available Date --}}
                        <div class="form-group">
                            <label class="form-label" for="available_date">Available Date</label>
                            <input type="date" name="available_date" id="available_date" class="form-control" required
                                   value="{{ old('available_date', optional($availability->available_date)->format('Y-m-d')) }}">
                            @error('available_date') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        {{-- Time --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" for="start_time">Start Time</label>
                                    <input type="time" name="start_time" id="start_time" class="form-control" required
                                           value="{{ old('start_time', $availability->start_time) }}">
                                    @error('start_time') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label" for="end_time">End Time</label>
                                    <input type="time" name="end_time" id="end_time" class="form-control"
                                           value="{{ old('end_time', $availability->end_time) }}">
                                    @error('end_time') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Capacity --}}
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label" for="capacity_total">Total Capacity</label>
                                <input type="number" name="capacity_total" id="capacity_total" class="form-control" min="0" required
                                       value="{{ old('capacity_total', $availability->capacity_total) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="capacity_booked">Booked</label>
                                <input type="number" name="capacity_booked" id="capacity_booked" class="form-control" min="0"
                                       value="{{ old('capacity_booked', $availability->capacity_booked) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="capacity_reserved">Reserved</label>
                                <input type="number" name="capacity_reserved" id="capacity_reserved" class="form-control" min="0"
                                       value="{{ old('capacity_reserved', $availability->capacity_reserved) }}">
                            </div>
                        </div>

                        {{-- Flags --}}
                        <div class="row mt-3">
                            <div class="col-md-4 form-check">
                                <input type="checkbox" class="form-check-input" id="unlimited_availability"
                                       name="unlimited_availability" value="1"
                                       {{ old('unlimited_availability', $availability->unlimited_availability) ? 'checked' : '' }}>
                                <label class="form-check-label" for="unlimited_availability">Unlimited Availability</label>
                            </div>

                            <div class="col-md-4 form-check">
                                <input type="checkbox" class="form-check-input" id="sold_out"
                                       name="sold_out" value="1"
                                       {{ old('sold_out', $availability->sold_out) ? 'checked' : '' }}>
                                <label class="form-check-label" for="sold_out">Sold Out</label>
                            </div>
                        </div>

                        {{-- Participants --}}
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label" for="min_participants">Min Participants</label>
                                <input type="number" name="min_participants" id="min_participants" class="form-control" min="1"
                                       value="{{ old('min_participants', $availability->min_participants) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="max_participants">Max Participants</label>
                                <input type="number" name="max_participants" id="max_participants" class="form-control" min="1"
                                       value="{{ old('max_participants', $availability->max_participants) }}">
                            </div>
                        </div>

                        {{-- Operational Flags --}}
                        <div class="row mt-3">
                            <div class="col-md-4 form-check">
                                <input type="checkbox" class="form-check-input" id="is_closed"
                                       name="is_closed" value="1"
                                       {{ old('is_closed', $availability->is_closed) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_closed">Closed</label>
                            </div>

                            <div class="col-md-4 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active"
                                       name="is_active" value="1"
                                       {{ old('is_active', $availability->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>

                        {{-- Submit --}}
                        <div class="form-group mt-4">
                            <button class="btn btn-primary">Update</button>
                            <a href="{{ route('availability.index') }}" class="btn btn-danger">Cancel</a>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
