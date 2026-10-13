@extends('backend.app')

@section('title', 'Plan Edit')

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
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Plan Form</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Plan</a></li>
                <li class="breadcrumb-item active" aria-current="page">Plan</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}


    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form method="post" action="{{ route('plan.update', ['id' => $data->id]) }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="title" class="form-label">Title:</label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" name="title"
                                placeholder="Enter title" id="title" value="{{ $data->title ?? old('title') }}">
                            @error('name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="price" class="form-label">Price:</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control @error('price') is-invalid @enderror"
                                    name="price" placeholder="Enter price" id="price"
                                    value="{{ $data->price ?? old('price') }}">
                            </div>
                            @error('price')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>


                        <div class="form-group">
                            <label for="duration" class="form-label">Duration (Days):</label>
                            <input type="number" class="form-control @error('number') is-invalid @enderror" name="duration"
                                placeholder="Enter duration" id="duration"
                                value="{{ $data->duration ?? old('duration') }}">
                            @error('duration')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="type" class="form-label">Type:</label>
                            <select name="type" id="type" class="select form-control">
                                <option value="Free" {{ old('type', $data->type ?? '') == 'Free' ? 'selected' : '' }}>Free
                                </option>
                                <option value="Monthly" {{ old('type', $data->type ?? '') == 'Monthly' ? 'selected' : '' }}>
                                    Monthly</option>
                                <option value="Yearly" {{ old('type', $data->type ?? '') == 'Yearly' ? 'selected' : '' }}>
                                    Yearly</option>
                            </select>
                            @error('type')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="interval" class="form-label">Interval:</label>
                            <select name="interval" id="interval" class="select form-control">
                                <option value="month"
                                    {{ old('interval', $data->interval ?? '') == 'month' ? 'selected' : '' }}>Monthly
                                </option>
                                <option value="year"
                                    {{ old('interval', $data->interval ?? '') == 'year' ? 'selected' : '' }}>Yearly
                                </option>
                            </select>
                            @error('interval')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>


                        <div class="form-group">
                            <label for="stripe_product_id" class="form-label">Stripe Product Id:</label>
                            <input type="text" class="form-control @error('stripe_product_id') is-invalid @enderror"
                                name="stripe_product_id" placeholder="Enter stripe product id" id="stripe_product_id"
                                value="{{ $data->stripe_product_id ?? old('stripe_product_id') }}">
                            @error('stripe_product_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="stripe_price_id" class="form-label">Stripe Price Id:</label>
                            <input type="text" class="form-control @error('stripe_price_id') is-invalid @enderror"
                                name="stripe_price_id" placeholder="Enter stripe price id" id="stripe_price_id"
                                value="{{ $data->stripe_price_id ?? old('stripe_price_id') }}">
                            @error('stripe_price_id')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Options:</label>
                                <button type="button" id="add-option" class="btn btn-success btn-sm">+ Add Option</button>
                            </div>

                            <div id="option-wrapper">
                                @foreach ($data->planOptions as $index => $option)
                                    <div class="row g-2 mb-2 option-row">
                                        <div class="col-md-5">
                                            <input type="text" name="options[{{ $index }}][name]"
                                                class="form-control" placeholder="Enter name" value="{{ $option->name }}">
                                        </div>
                                        <div class="col-md-5">
                                            <select name="options[{{ $index }}][type]" class="form-control">
                                                <option value="">Select type</option>
                                                <option value="Yes" {{ $option->type === 'Yes' ? 'selected' : '' }}>Yes
                                                </option>
                                                <option value="No" {{ $option->type === 'No' ? 'selected' : '' }}>No
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button"
                                                class="btn btn-danger remove-option w-100">Remove</button>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- If no existing options, show one empty row --}}
                                @if ($data->planOptions->isEmpty())
                                    <div class="row g-2 mb-2 option-row">
                                        <div class="col-md-5">
                                            <input type="text" name="options[0][name]" class="form-control"
                                                placeholder="Enter name">
                                        </div>
                                        <div class="col-md-5">
                                            <select name="options[0][type]" class="form-control">
                                                <option value="">Select type</option>
                                                <option value="Yes">Yes</option>
                                                <option value="No">No</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button"
                                                class="btn btn-danger remove-option w-100">Remove</button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                        </div>

                        <div class="form-group">
                            <button class="btn btn-primary" type="submit">Submit</button>
                            <a href="{{ route('plan.index') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let index = {{ $data->planOptions->count() ?: 1 }};

            // Add new option row
            $('#add-option').on('click', function() {
                let newRow = `
            <div class="row g-2 mb-2 option-row">
                <div class="col-md-5">
                    <input type="text" name="options[${index}][name]" class="form-control" placeholder="Enter name">
                </div>
                <div class="col-md-5">
                    <select name="options[${index}][type]" class="form-control">
                        <option value="">Select type</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger remove-option w-100">Remove</button>
                </div>
            </div>
        `;
                $('#option-wrapper').append(newRow);
                index++;
            });

            // Remove option row
            $(document).on('click', '.remove-option', function() {
                $(this).closest('.option-row').remove();

                // If all rows removed, add one empty row again
                if ($('#option-wrapper .option-row').length === 0) {
                    $('#option-wrapper').append(`
                <div class="row g-2 mb-2 option-row">
                    <div class="col-md-5">
                        <input type="text" name="options[0][name]" class="form-control" placeholder="Enter name">
                    </div>
                    <div class="col-md-5">
                        <select name="options[0][type]" class="form-control">
                            <option value="">Select type</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger remove-option w-100">Remove</button>
                    </div>
                </div>
            `);
                    index = 1;
                }
            });
        });
    </script>
@endpush
