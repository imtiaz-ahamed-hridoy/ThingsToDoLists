@extends('errors.minimal')

@section('title', '402 Payment Required')

@section('content')
<div class="page-content error-page error2">
    <div class="container text-center">
        <div class="error-template">
            <h2 class="text-white mb-2">402<span class="fs-20"> Payment Required</span></h2>
            <h5 class="error-details text-white">
                Oops! Payment is required to access this resource.
            </h5>
        </div>
    </div>
</div>
@endsection
