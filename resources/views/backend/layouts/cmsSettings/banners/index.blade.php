@extends('backend.app')

@section('title', 'Home Page Banner')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Home Page Banner</h3>
    </div>
    <div class="card-body">
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="d-flex justify-content-center py-5">
            <div class="text-center">
                <i class="fa fa-arrow-right" style="font-size:48px;color:#6c757d;"></i>
                <h4 class="mt-3">Single Banner Management</h4>
                <p class="text-muted">Click below to manage the home page banner settings.</p>
                <a href="{{ route('cmsSettings.banners.edit', $banner->id) }}" class="btn btn-primary btn-lg">
                    <i class="fa fa-cog"></i> Manage Banner
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
