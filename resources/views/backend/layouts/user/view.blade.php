@extends('backend.app')

@section('title', 'User Details')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">User Details</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('store.index') }}">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page">User Details</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}

    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-5">
                <div class="card-header border-bottom text-white"
                    style="margin-bottom: 0; display: flex; justify-content: space-between; background-color: #4CB8C4;">
                    <h3 class="card-title">User Details</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered border-bottom">
                        <tbody>
                              <tr>
                                <th>User Name</th>
                                <td>{{ $data->first_name ?? ' '}} {{$data->last_name ?? ' ' }}</td>
                            </tr>

                            <tr>
                                <th>Email</th>
                                <td>{{ $data->email ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td>{{ $data->number ?? 'NULL' }}</td>
                            </tr>
                            <tr>
                                <th>Address</th>
                                <td>{{ $data->address ?? 'NULL' }}</td>
                            </tr>
                            <tr>
                                <th>Date Of Birth</th>
                                <td>{{ $data->dob ?? 'NULL' }}</td>
                            </tr>
                           
                            {{-- <tr>
                                <th>Email</th>
                                <td>{{ $data->email ?? '' }}</td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td>{{ $data->phone ?? ' ' }}</td>
                            </tr>
                                <tr>
                                <th>Whatsapp</th>
                                <td>{{ $data->whatsapp ?? '' }}</td>
                            </tr>

                            <tr>
                                <th>Website</th>
                                <td>{{ $data->website ?? 'NULL' }}</td>
                            </tr>
                              <tr>
                                <th>Address</th>
                                <td>{{ $data->address ?? 'NULL' }}</td>
                            </tr>
                              <tr>
                                <th>Details</th>
                                <td>{{ $data->details ?? 'NULL' }}</td>
                            </tr>
                              <tr>
                                <th>Allow Reservation?</th>
                                <td>
                                    @if ($data->reservation == True)
                                        True
                                    @else
                                        False
                                    @endif
                                </td>
                            </tr>
                             <tr>
                                <td>Social</td>
                                <td>
                                    @php
                                        $socials = is_string($data->social) ? json_decode($data->social, true) : $data->social;
                                    @endphp

                                    @if(!empty($socials))
                                        @foreach ($socials as $platform => $url)
                                            @if(!empty($url))
                                                <a href="{{ $url }}" target="_blank" class="me-2 text-decoration-underline">
                                                    {{ ucfirst($platform) }}
                                                </a>
                                            @endif
                                        @endforeach
                                    @else
                                        <span class="text-muted">No social links</span>
                                    @endif
                                </td>
                            </tr> --}}

                            
                            <tr>
                                <th>Role</th>
                                <td>
                                    @if ($data->status== 'Admin')
                                        <span class="badge bg-success">Admin</span>
                                    @else
                                        <span class="badge bg-info">User</span>
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
                            <a href="{{ route('store.index') }}" class="btn btn-danger me-2">Back</a>
                        </div>
                </div>
            </div>
        </div>
    </div>
@endsection
