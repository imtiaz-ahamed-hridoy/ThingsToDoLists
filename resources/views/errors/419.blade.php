@extends('errors.minimal')

@section('title', '419 Session Expired')

@section('content')
<div class="page-content error-page error2">
    <div class="container text-center">
        <div class="error-template">
            <h2 class="text-white mb-2">419<span class="fs-20"> Session Expired</span></h2>
            <h5 class="error-details text-white">
                Oops! Your session has expired. Please refresh the page and try again.
            </h5>
        </div>
    </div>
</div>
@endsection
