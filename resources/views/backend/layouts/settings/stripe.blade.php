@extends('backend.app')

@section('title', 'Stripe Settings')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Stripe Settings</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0);">Settings</a></li>
                <li class="breadcrumb-item active" aria-current="page">Stripe Settings</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER --}}


    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-0">
                <div class="card-body">
                    <form action="{{ route('stripe.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="stripe_key" class="form-label">Stripe Key</label>
                            <input type="text" class="form-control" id="stripe_key" name="stripe_key"
                                value="{{ old('stripe_key', $stripe_key) }}" required>
                            @error('stripe_key')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="stripe_secret" class="form-label">Stripe Secret</label>
                            <input type="text" class="form-control" id="stripe_secret" name="stripe_secret"
                                value="{{ old('stripe_secret', $stripe_secret) }}" required>
                            @error('stripe_secret')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                         <div class="mb-3">
                            <label for="webhook_key" class="form-label">Webhook Secret</label>
                            <input type="text" class="form-control" id="webhook_key" name="webhook_key"
                                value="{{ old('webhook_key', $webhook_key) }}" required>
                            @error('webhook_key')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
