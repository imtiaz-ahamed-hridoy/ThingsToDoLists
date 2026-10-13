@extends('errors.minimal')

@section('title', '401 Unauthorized')

@section('content')
<div class="page-content error-page error2">
    <div class="container text-center">
        <div class="error-template">
            <h2 class="text-white mb-2">401<span class="fs-20"> Unauthorized</span></h2>
            <h5 class="error-details text-white">
                Oops! You are not authorized to access this page.
            </h5>
            <div class="text-center">
                <a class="btn btn-primary mt-5 mb-5" href="{{ route('login') }}"> <i class="fa fa-sign-in"></i> Login to Continue </a>
            </div>
        </div>
    </div>
</div>
@endsection
