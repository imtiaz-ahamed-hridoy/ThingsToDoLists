@extends('errors.minimal')

@section('title', '403 Forbidden')

@section('content')
<div class="page-content error-page error2">
    <div class="container text-center">
        <div class="error-template">
            <h2 class="text-white mb-2">403<span class="fs-20"> Forbidden</span></h2>
            <h5 class="error-details text-white">
                Oops! You do not have permission to access this page.
            </h5>
            <div class="text-center">
                <a class="btn btn-primary mt-5 mb-5" href="{{ route('dashboard') }}"> <i class="fa fa-long-arrow-left"></i> Back to Dashboard </a>
            </div>
        </div>
    </div>
</div>
@endsection
