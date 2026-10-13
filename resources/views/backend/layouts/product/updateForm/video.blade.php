{{-- =========================
     PRODUCT VIDEOS — Edit (same simple approach as banner)
========================= --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Product Videos</h4>
        <small class="text-muted">Upload video files (mp4, mov, avi, mkv, webm). Max 200MB per video.</small>
    </div>
    <div class="card-body">

        {{-- Existing Videos --}}
        @if(isset($product) && $product->productVideos && $product->productVideos->isNotEmpty())
        <div class="mb-4">
            <label class="form-label fw-semibold">Current Videos</label>
            <div class="row g-3">
                @foreach($product->productVideos as $pv)
                <div class="col-md-4" id="existing_video_{{ $pv->id }}">
                    <div class="border rounded p-2 text-center">
                        @php
                            $rawPath = $pv->getRawOriginal('source_url');
                            $videoSrc = $rawPath
                                ? \Illuminate\Support\Facades\Storage::disk('public')->url(ltrim($rawPath, '/'))
                                : null;
                        @endphp
                        @if($videoSrc)
                        <video controls style="width:100%;max-height:160px;border-radius:4px;">
                            <source src="{{ $videoSrc }}" type="video/mp4">
                        </video>
                        @else
                        <div class="py-3 text-muted"><i class="fa fa-video-camera fa-2x"></i></div>
                        @endif
                        <div class="mt-1">
                            <small class="text-muted d-block">{{ $pv->title ?? basename($rawPath ?? 'video') }}</small>
                            <label class="text-danger small mt-1">
                                <input type="checkbox" name="remove_videos[]" value="{{ $pv->id }}">
                                Remove this video
                            </label>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Upload new videos — same as banner --}}
        <div class="mb-3">
            <label class="form-label fw-semibold">Add New Video Files</label>
            <input type="file"
                   id="video_files_input"
                   name="video_files[]"
                   class="form-control"
                   accept="video/*"
                   multiple>
            <small class="text-muted">Accepted: MP4, WebM, MOV, AVI, MKV (Max: 200MB each)</small>
        </div>

        {{-- Live preview for newly selected files --}}
        <div id="video_preview_list"></div>

    </div>
</div>

<script>
(function () {
    const input = document.getElementById('video_files_input');
    const previewList = document.getElementById('video_preview_list');

    if (!input) return;

    input.addEventListener('change', function () {
        previewList.innerHTML = '';
        const files = Array.from(this.files);

        files.forEach(function (file) {
            const url = URL.createObjectURL(file);
            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex align-items-center gap-3 mb-2 p-2 border rounded';
            wrapper.innerHTML = `
                <video src="${url}" controls style="width:120px;height:70px;border-radius:4px;object-fit:cover;flex-shrink:0;"></video>
                <div>
                    <div class="fw-semibold">${file.name}</div>
                    <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                </div>
            `;
            previewList.appendChild(wrapper);
        });
    });
}());
</script>
