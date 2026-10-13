@extends('backend.app')

@section('title', 'Create Blog Post')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Create Blog Post</h3>
        <div class="card-options">
            <a href="{{ route('cmsSettings.blogs.index') }}" class="btn btn-default">Back</a>
        </div>
    </div>
    <div class="card-body">
        @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <form action="{{ route('cmsSettings.blogs.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label>Slug (optional)</label>
                        <input type="text" name="slug" class="form-control" value="{{ old('slug') }}" placeholder="Auto-generated if empty">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label>Category</label>
                        <select name="category_id" class="form-control mb-2">
                            <option value="">-- Select --</option>
                            @foreach($categories as $c)
                            <option value="{{ $c->id }}" {{ old('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Or add new category:</small>
                        <input type="text" name="category_new" class="form-control" placeholder="e.g. Travel" value="{{ old('category_new') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label>Author Name</label>
                        <input type="text" name="author_name" class="form-control" value="{{ old('author_name') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label>Minutes to Read</label>
                        <input type="number" name="minutes_to_read" class="form-control" value="{{ old('minutes_to_read') }}" min="1" max="999" placeholder="e.g. 5">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label>Excerpt</label>
                <textarea name="excerpt" class="form-control" rows="3">{{ old('excerpt') }}</textarea>
            </div>

            <div class="mb-3">
                <label>Content (HTML)</label>
                <textarea name="content" class="form-control summernote" rows="8">{{ old('content') }}</textarea>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label>Featured Image</label>
                        <input type="file" name="featured_image" class="form-control" accept="image/*">
                        <small class="text-muted">Accepted: jpeg, png, jpg, gif, webp, svg (max 10MB)</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label>Featured Post</label><br>
                        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}>
                        <small class="text-muted d-block">Only one post can be featured</small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label>Published</label><br>
                        <input type="checkbox" name="published" value="1" {{ old('published') ? 'checked' : '' }}>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label>Tags (select)</label>
                        <select name="tags[]" class="form-control" multiple>
                            @foreach($tags as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Or add new tags (comma-separated):</small>
                        <input type="text" name="tags_new" class="form-control" placeholder="e.g. Travel, Tips">
                    </div>
                </div>
            </div>

            <hr>
            <h5>Sub-Articles <small class="text-muted">(Optional child articles within this blog post)</small></h5>
            <div id="children-container">
                <div class="child-item card card-body mb-3" data-index="0">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label>Article Title</label>
                                <input type="text" name="child_title[]" class="form-control" placeholder="Sistine Chapel Ceiling">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label>Location / Subtitle</label>
                                <input type="text" name="child_subtitle[]" class="form-control" placeholder="Vatican City">
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label>Description</label>
                        <textarea name="child_description[]" class="form-control" rows="3" placeholder="Full description of this article..."></textarea>
                    </div>
                    <div class="mb-2">
                        <label>Image</label>
                        <input type="file" name="child_image[]" class="form-control" accept="image/*">
                    </div>
                    <button type="button" class="btn btn-sm btn-danger remove-child" onclick="this.closest('.child-item').remove()">Remove</button>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-secondary mb-3" id="add-child-btn">+ Add Sub-Article</button>

            <hr>
            <button class="btn btn-primary">Save Blog Post</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<link href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-lite.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('.summernote').summernote({
            height: 300
        });

        let childIndex = 1;
        document.getElementById('add-child-btn').addEventListener('click', function() {
            const container = document.getElementById('children-container');
            const template = `
                <div class="child-item card card-body mb-3" data-index="${childIndex}">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label>Article Title</label>
                                <input type="text" name="child_title[]" class="form-control" placeholder="Sistine Chapel Ceiling">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label>Location / Subtitle</label>
                                <input type="text" name="child_subtitle[]" class="form-control" placeholder="Vatican City">
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label>Description</label>
                        <textarea name="child_description[]" class="form-control" rows="3" placeholder="Full description of this article..."></textarea>
                    </div>
                    <div class="mb-2">
                        <label>Image</label>
                        <input type="file" name="child_image[]" class="form-control" accept="image/*">
                    </div>
                    <button type="button" class="btn btn-sm btn-danger remove-child" onclick="this.closest('.child-item').remove()">Remove</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', template);
            childIndex++;
        });
    });
</script>
@endpush
