@extends('backend.app')

@section('title', 'Product Details')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Product Details</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('store.index') }}">Products</a></li>
                <li class="breadcrumb-item active" aria-current="page">Product Details</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}

    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-5">
                <div class="card-header border-bottom text-white"
                    style="margin-bottom: 0; display: flex; justify-content: space-between; background-color: #4CB8C4;">
                    <h3 class="card-title">Product Details</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered border-bottom">
                        <tbody>
                            <tr>
                                <th>Store Name</th>
                                <td>{{ $data->store->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>User Name</th>
                                <td>{{ $data->store->user->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Product Name</th>
                                <td>{{ $data->name ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Offer Type</th>
                                 <td>
                                    <span class="badge bg-info">{{ $data->offer_type ?? 'No Offer Available' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Offer Description</th>
                                 <td>
                                   {{ $data->offer_des ?? 'N/A' }}
                                </td>
                            </tr>
                            <tr>
                                <th>Tags</th>
                                <td>
                                    <span class="badge bg-primary">{{ $data->tags }}</span>
                                </td>

                            </tr>
                            <tr>
                                <th>Price</th>
                                <td>{{ $data->price ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Photo</th>
                                <td>
                                    @if ($data->photo)
                                        <img src="{{ asset('storage/' . $data->photo) }}" alt="Thumbnail"
                                            style="max-width: 150px; max-height: 150px;">
                                    @else
                                        No Photo Available
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Category</th>
                                <td>
                                    @forelse($data->categories as $category)
                                        <span class="badge bg-info">{{ $category->category ?? $category->name }}</span>
                                    @empty
                                        No Category
                                    @endforelse
                                </td>
                            </tr>
                            <tr>
                                <th>Visitor Counts</th>
                                <td>{{ $data->viewers ? $data->viewers->count() : 0 }}</td>
                            </tr>
                            <tr>
                                <td>Visitors</td>
                                <td>
                                    @forelse($data->viewers as $visitor)
                                        <span
                                            class="badge text-black">{{ $visitor->visitors ?? 'N/A' }},</span>
                                    @empty
                                        No Visitors
                                    @endforelse
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if ($data->status == 'Active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>

                            <tr>
                                <th>Created At</th>
                                <td>
                                    @if ($data->created_at)
                                        {{ $data->created_at->diffForHumans() }}
                                    @else
                                        &nbsp;
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Updated At</th>
                                <td>
                                    @if ($data->updated_at)
                                        {{ $data->updated_at->diffForHumans() }}
                                    @else
                                        &nbsp;
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="form-group">
                        <a href="{{ route('product.index') }}" class="btn btn-danger me-2">Back</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
