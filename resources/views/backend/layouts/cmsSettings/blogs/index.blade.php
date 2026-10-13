@extends('backend.app')

@section('title', 'Blog Posts')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Blog Posts</h3>
        <div class="card-options">
            <a href="{{ route('cmsSettings.blogs.create') }}" class="btn btn-primary">Create Post</a>
        </div>
    </div>
    <div class="card-body">
        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Sub-Articles</th>
                        <th>Featured</th>
                        <th>Published</th>
                        <th>Published At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($posts as $p)
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td>
                            @if($p->featured_image_url)
                            <img src="{{ $p->featured_image_url }}" alt="image" style="height:60px;width:60px;object-fit:cover;border-radius:4px;" />
                            @else
                            <span class="text-muted">No image</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $p->title }}</strong>
                            @if($p->author_name)
                            <br><small class="text-muted">by {{ $p->author_name }}</small>
                            @endif
                        </td>
                        <td>{{ $p->category?->name ?? '—' }}</td>
                        <td class="text-center">{{ $p->articles_count ?? 0 }}</td>
                        <td class="text-center">
                            @if($p->is_featured)
                            <span class="badge bg-warning">Featured</span>
                            @else
                            <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                        <td>{{ $p->published ? 'Yes' : 'No' }}</td>
                        <td>{{ $p->published_at ? $p->published_at->format('M j, Y') : '—' }}</td>
                        <td>
                            <a href="{{ route('cmsSettings.blogs.edit', $p->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                            <form action="{{ route('cmsSettings.blogs.destroy', $p->id) }}" method="POST" style="display:inline-block;">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this post?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center">No blog posts found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
