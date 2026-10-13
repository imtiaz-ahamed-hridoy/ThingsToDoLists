@extends('errors.minimal')

@section('title', '429 Too Many Requests')

@section('content')
<div class="page-content error-page error2">
    <div class="container text-center">
        <div class="error-template">
            <h2 class="text-white mb-2">429<span class="fs-20"> Too Many Requests</span></h2>
            <h5 class="error-details text-white">
                Oops! You have sent too many requests in a short period. Please try again later.
            </h5>
        </div>
    </div>
</div>
@endsection
