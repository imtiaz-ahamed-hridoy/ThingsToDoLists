@extends('backend.app')

@section('title', 'Home Page Sections')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Home Page Sections</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <!-- Add Section button removed -->
        </div>
    </div>
    {{-- PAGE-HEADER --}}

    <div class="row row-sm">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <h3 class="card-title">Sections List</h3>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if($sections->isEmpty())
                        <div class="text-center py-5">
                            <i class="fe fe-layers fs-40 text-muted mb-3 d-block"></i>
                            <h5>No Sections Yet</h5>
                            <p class="text-muted">No homepage sections are currently available.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap border-bottom" id="sections-table">
                                <thead>
                                    <tr>
                                        <th class="wd-5p border-bottom-0">SN</th>
                                        <th class="wd-15p border-bottom-0">Image</th>
                                        <th class="wd-20p border-bottom-0">Title</th>
                                        <th class="wd-25p border-bottom-0">Short Description</th>
                                        <th class="wd-15p border-bottom-0">Content</th>
                                        <th class="wd-5p border-bottom-0">Status</th>
                                        <th class="wd-15p border-bottom-0">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sections as $key => $section)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                @if($section->image)
                                                    <img src="{{ $section->image }}" alt="{{ $section->title }}" width="80" height="60" style="object-fit: cover; border-radius: 4px;">
                                                @else
                                                    <span class="text-muted">No Image</span>
                                                @endif
                                            </td>
                                            <td>{{ $section->title ?? 'Untitled' }}</td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 250px;">
                                                    {{ Str::limit($section->short_description, 80) ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                @if($section->section_type === 'locations')
                                                    <span class="badge bg-info">{{ $section->locations->count() }} Locations</span>
                                                @else
                                                    @if(!empty($section->product_ids))
                                                        <span class="badge bg-primary">{{ count($section->product_ids) }} Products</span>
                                                    @else
                                                        <span class="badge bg-secondary">0</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('cmsSettings.sections.toggleStatus', $section->id) }}" class="text-decoration-none">
                                                    @if($section->status)
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-danger">Inactive</span>
                                                    @endif
                                                </a>
                                            </td>
                                            <td>
                                                <a href="{{ route('cmsSettings.sections.edit', $section->id) }}" class="btn btn-primary btn-sm">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection


