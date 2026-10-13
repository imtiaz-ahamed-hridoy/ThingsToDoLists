@extends('backend.app')

@section('title', 'Testimonials')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Testimonials</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <a href="{{ route('cmsSettings.testimonial.create') }}" class="btn btn-primary btn-icon text-white me-2">
                <span>
                    <i class="fe fe-plus"></i>
                </span> Add Testimonial
            </a>
        </div>
    </div>

    <div class="row row-sm">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <h3 class="card-title">Testimonial List</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered text-nowrap border-bottom" id="basic-datatable">
                            <thead>
                                <tr>
                                    <th class="wd-15p border-bottom-0">SN</th>
                                    <th class="wd-15p border-bottom-0">Avatar</th>
                                    <th class="wd-20p border-bottom-0">Name</th>
                                    <th class="wd-15p border-bottom-0">Country</th>
                                    <th class="wd-10p border-bottom-0">Rating</th>
                                    <th class="wd-10p border-bottom-0">Status</th>
                                    <th class="wd-25p border-bottom-0">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($testimonials as $key => $testimonial)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            @if($testimonial->avater)
                                                <img src="{{ asset('storage/'.$testimonial->avater) }}" alt="{{ $testimonial->name }}" width="50" style="border-radius: 50%;">
                                            @elseif($testimonial->image)
                                                <img src="{{ asset('storage/'.$testimonial->image) }}" alt="{{ $testimonial->name }}" width="50">
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>{{ $testimonial->name }}</td>
                                        <td>
                                            @if($testimonial->country_icon)
                                                @if(str_starts_with($testimonial->country_icon, 'cms/') || filter_var($testimonial->country_icon, FILTER_VALIDATE_URL))
                                                    <img src="{{ asset('storage/'.$testimonial->country_icon) }}" width="20" class="me-1">
                                                @else
                                                    <span class="me-1">{!! $testimonial->country_icon !!}</span>
                                                @endif
                                            @endif
                                            {{ $testimonial->country }}
                                        </td>
                                        <td>{{ $testimonial->rating }}</td>
                                        <td>
                                            @if($testimonial->status)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('cmsSettings.testimonial.edit', $testimonial->id) }}" class="btn btn-primary btn-sm">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <form action="{{ route('cmsSettings.testimonial.destroy', $testimonial->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
