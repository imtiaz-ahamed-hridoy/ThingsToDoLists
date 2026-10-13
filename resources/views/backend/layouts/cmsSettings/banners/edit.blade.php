@extends('backend.app')

@section('title', 'Home Page Banner Settings')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Home Page Banner Settings</h3>
        <div class="card-options">
            <span class="badge bg-info">Single Banner</span>
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('cmsSettings.banners.update', $banner->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-8">
                    <!-- Title -->
                    <div class="form-group mb-3">
                        <label class="form-label">Banner Title</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', $banner->title) }}" placeholder="Enter banner title">
                    </div>

                    <!-- External Video URL -->
                    <div class="form-group mb-3">
                        <label class="form-label">External Video URL</label>
                        <input type="text" name="video_url" class="form-control" placeholder="https://example.com/video.mp4" value="{{ old('video_url', $banner->getRawOriginal('video_url')) }}">
                        <small class="text-muted">Provide a direct link to an external video, OR upload a file below.</small>
                        @if($banner->video_url && !$banner->getRawOriginal('video_path'))
                        <div class="mt-2">
                            <a href="{{ $banner->video_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-external-link"></i> Preview External Video
                            </a>
                        </div>
                        @endif
                    </div>

                    <!-- Upload Video File -->
                    <div class="form-group mb-3">
                        <label class="form-label">Upload Video File</label>
                        <input type="file" name="video_file" class="form-control" accept="video/*">
                        <small class="text-muted">Accepted: MP4, WebM, MOV, AVI, MKV, FLV, WMV, M4V, 3GP, OGG, QuickTime (Max: 200MB)</small>
                    </div>



                    <!-- Status -->
                    <div class="form-group mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="1" {{ $banner->status ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ !$banner->status ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Banner Settings
                    </button>
                </div>

                <div class="col-md-4">
                    <!-- Video Preview -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title">Video Preview</h5>
                        </div>
                        <div class="card-body text-center">
                            @php
                                $videoUrl = $banner->getRawOriginal('video_path')
                                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($banner->getRawOriginal('video_path'))
                                    : $banner->video_url;
                            @endphp

                            @if($videoUrl)
                            <video controls style="width:100%;max-height:200px;border-radius:4px;">
                                <source src="{{ $videoUrl }}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                            <div class="mt-2">
                                @if($banner->getRawOriginal('video_path'))
                                <div class="form-check">
                                    <input type="checkbox" name="clear_video" value="1" class="form-check-input" id="clearVideo">
                                    <label class="form-check-label text-danger" for="clearVideo">Remove uploaded video</label>
                                </div>
                                <small class="text-muted d-block">
                                    <a href="{{ $videoUrl }}" target="_blank">Open video in new tab</a>
                                </small>
                                @endif
                            </div>
                            @else
                            <div class="py-4 text-muted">
                                <i class="fa fa-video-camera" style="font-size:48px;"></i>
                                <p class="mt-2">No video uploaded yet.</p>
                                <p class="small">Upload a video file or provide an external URL.</p>
                            </div>
                            @endif
                        </div>
                    </div>



                    <!-- Current Data Summary -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Banner Info</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li><strong>ID:</strong> #{{ $banner->id }}</li>
                                <li><strong>Status:</strong> {!! $banner->status ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' !!}</li>
                                <li><strong>Video Source:</strong>
                                    @if($banner->getRawOriginal('video_path'))
                                    Uploaded File
                                    @elseif($banner->video_url)
                                    External URL
                                    @else
                                    <span class="text-muted">None</span>
                                    @endif
                                </li>

                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Preview uploaded video before form submission
    document.querySelector('input[name="video_file"]')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const url = URL.createObjectURL(file);
            const videoPreview = document.querySelector('.card-body video');
            // If we have an existing video preview, update it
            if (videoPreview) {
                videoPreview.querySelector('source').src = url;
                videoPreview.load();
            }
        }
    });


</script>
@endsection
