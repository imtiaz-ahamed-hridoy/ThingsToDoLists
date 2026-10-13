@extends('backend.app')

@section('title', 'Payment Details')

@section('content')
    {{-- PAGE-HEADER --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Payment Details</h1>
        </div>
        <div class="ms-auto pageheader-btn">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('store.index') }}">Payment</a></li>
                <li class="breadcrumb-item active" aria-current="page">Payment Details</li>
            </ol>
        </div>
    </div>
    {{-- PAGE-HEADER END --}}

    <div class="row">
        <div class="col-lg-12 col-xl-12 col-md-12 col-sm-12">
            <div class="card box-shadow-5">
                <div class="card-header border-bottom text-white"
                    style="margin-bottom: 0; display: flex; justify-content: space-between; background-color: #4CB8C4;">
                    <h3 class="card-title">Payment Details</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered border-bottom">
                        <tbody>
                              <tr>
                                <th>User Name</th>
                                <td>{{ $data->user->name ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Plan Name</th>
                                <td>{{ $data->plan->name ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Product Name</th>
                                <td>{{ $data->plan->stripe_plan_id ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Price</th>
                                <td>{{ $data->plan->stripe_price_id ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Trnx Id</th>
                                <td>{{ $data->transaction_id ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Payment Id</th>
                                <td>{{ $data->stripe_payment_id ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Session Id</th>
                                <td>{{ $data->stripe_session_id ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Subscription Starts</th>
                                <td>{{ $data->start_date ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Subscription Ends</th>
                                <td>{{ $data->end_date ?? ' ' }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if ($data->status== 'Active')
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-info">{{ ucfirst($data->status) }}</span>
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
                            <a href="{{ route('payment.index') }}" class="btn btn-danger me-2">Back</a>
                        </div>
                </div>
            </div>
        </div>
    </div>
@endsection
