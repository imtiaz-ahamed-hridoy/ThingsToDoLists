@extends('backend.app')

@section('title', 'Create Availability Slot')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Availability Slot Form</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Create</a></li>
                <li class="breadcrumb-item active" aria-current="page">Availability Slot</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}

    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if($errors->has('duplicate'))
                        <div class="alert alert-danger">{{ $errors->first('duplicate') }}</div>
                    @endif

                    <form method="post" action="{{ route('availability.store') }}">
                        @csrf

                        {{-- Product --}}
                        <div class="form-group">
                            <label for="product_id" class="form-label">Product</label>
                            <select name="product_id" id="product_id" class="form-control" required>
                                <option value="">Select Product</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                        {{ $product->title }}
                                    </option>
                                @endforeach
                            </select>
                            @error('product_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Provider --}}
                        <div class="form-group">
                            <label for="provider" class="form-label">Provider</label>
                            <input type="text" name="provider" id="provider" class="form-control" value="{{ old('provider') }}">
                            @error('provider')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Available Date --}}
                        <div class="form-group">
                            <label for="available_date" class="form-label">Available Date</label>
                            <input type="date" name="available_date" id="available_date" class="form-control" value="{{ old('available_date') }}" required>
                            @error('available_date')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        {{-- Start / End Time --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_time" class="form-label">Start Time</label>
                                    <input type="time" name="start_time" id="start_time" class="form-control" value="{{ old('start_time') }}" required>
                                    @error('start_time')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_time" class="form-label">End Time</label>
                                    <input type="time" name="end_time" id="end_time" class="form-control" value="{{ old('end_time') }}">
                                    @error('end_time')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Capacity --}}
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="capacity_total" class="form-label">Total Capacity</label>
                                    <input type="number" name="capacity_total" id="capacity_total" class="form-control" min="0" value="{{ old('capacity_total') }}" required>
                                    @error('capacity_total')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="capacity_booked" class="form-label">Booked Capacity</label>
                                    <input type="number" name="capacity_booked" id="capacity_booked" class="form-control" min="0" value="{{ old('capacity_booked', 0) }}">
                                    @error('capacity_booked')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="capacity_reserved" class="form-label">Reserved Capacity</label>
                                    <input type="number" name="capacity_reserved" id="capacity_reserved" class="form-control" min="0" value="{{ old('capacity_reserved', 0) }}">
                                    @error('capacity_reserved')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Flags --}}
                        <div class="row mb-3">
                            <div class="col-md-4 form-check">
                                <input type="checkbox" name="unlimited_availability" id="unlimited_availability" class="form-check-input" {{ old('unlimited_availability') ? 'checked' : '' }}>
                                <label class="form-check-label" for="unlimited_availability">Unlimited Availability</label>
                            </div>
                            <div class="col-md-4 form-check">
                                <input type="checkbox" name="sold_out" id="sold_out" class="form-check-input" {{ old('sold_out') ? 'checked' : '' }}>
                                <label class="form-check-label" for="sold_out">Sold Out</label>
                            </div>
                        </div>

                        {{-- Participants --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="min_participants" class="form-label">Minimum Participants</label>
                                    <input type="number" name="min_participants" id="min_participants" class="form-control" min="1" value="{{ old('min_participants', 1) }}">
                                    @error('min_participants')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="max_participants" class="form-label">Maximum Participants</label>
                                    <input type="number" name="max_participants" id="max_participants" class="form-control" min="1" value="{{ old('max_participants') }}">
                                    @error('max_participants')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Operational Flags --}}
                        <div class="row mb-3">
                            <div class="col-md-4 form-check">
                                <input type="checkbox" name="is_closed" id="is_closed" class="form-check-input" {{ old('is_closed') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_closed">Closed</label>
                            </div>
                            
                            <div class="col-md-4 form-check">
                                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>

                        {{-- Submit Actions --}}
                        <div class="form-group mt-3">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('availability.index') }}" class="btn btn-danger">Cancel</a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
