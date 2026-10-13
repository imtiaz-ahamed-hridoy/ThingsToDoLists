{{-- =========================
     PRODUCT VIDEOS — Simple upload (same approach as banner)
========================= --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Product Videos</h4>
        <small class="text-muted">Upload video files (mp4, mov, avi, mkv, webm). Max 200MB per video. You can add multiple.</small>
    </div>
    <div class="card-body">

        {{-- File input — standard direct upload, same as banner --}}
        <div class="mb-3">
            <label class="form-label fw-semibold">Select Video Files</label>
            <input type="file"
                   id="video_files_input"
                   name="video_files[]"
                   class="form-control"
                   accept="video/*"
                   multiple>
            <small class="text-muted">Accepted: MP4, WebM, MOV, AVI, MKV (Max: 200MB each)</small>
        </div>

        {{-- Live preview area — populated by JS on file select --}}
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
