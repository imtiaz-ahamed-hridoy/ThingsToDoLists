@extends('backend.app')

@section('title', 'Add Payment')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Add Payment</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('payment.store') }}">
                @csrf
                <div class="form-group mb-3">
                    <label for="users_id" class="form-label">Select User:</label>
                    <select name="user_id" id="user_id" class="form-control" required>
                        <option value="">-- Choose User --</option>
                        @foreach ($users as $users)
                            <option value="{{ $users->id }}">{{ $users->first_name }} {{ $users->last_name }}</option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                 <div class="form-group mb-3">
                    <label for="plan_id" class="form-label">Choose Plan:</label>
                    <select name="plan_id" id="plan_id" class="form-control" required>
                        <option value="">-- Choose Plan --</option>
                        @foreach ($plans as $plans)
                            <option value="{{ $plans->id }}">{{ $plans->title }}</option>
                        @endforeach
                    </select>
                    @error('plans')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Go To Payment Panel</button>
                <a href="{{ route('payment.index') }}" class="btn btn-danger">Cancel</a>
            </form>
        </div>
    </div>

@endsection
