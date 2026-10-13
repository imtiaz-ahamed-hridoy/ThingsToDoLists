@extends('errors.minimal')

@section('title', '503 Service Unavailable')

@section('content')
<div class="page-content error-page error2">
    <div class="container text-center">
        <div class="error-template">
            <h2 class="text-white mb-2">503<span class="fs-20"> Service Unavailable</span></h2>
            <h5 class="error-details text-white">
                Oops! The service is temporarily unavailable. Please try again later.
            </h5>
            <div class="text-center">
                <a class="btn btn-primary mt-5 mb-5" href="{{ route('welcome') }}">
                    <i class="fa fa-long-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
