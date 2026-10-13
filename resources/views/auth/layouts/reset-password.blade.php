@extends('auth.app')

@section('title', 'Reset Password')

@section('content')
    <div class="wrap-login100 p-0">
        <div class="card-body">
            <form class="login100-form validate-form" action="{{ route('password.store') }}" method="post">
                @csrf
                <div class="text-center">
                    <span class="login100-form-title">
                        Reset Password
                    </span>
                    <p class="text-muted">Enter your new password</p>
                </div>
                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="form-group">
                    <label class="form-label" for="eMail">E-Mail:</label>
                    <input class="form-control" id="eMail" placeholder="Enter Your Email" type="email" name="email"
                        value="{{ old('email', $request->emai) }}">
                    @error('email')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">New Password:</label>
                    <input class="form-control" id="password" placeholder="Enter New Password" type="password"
                        name="password">
                    @error('password')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm Password:</label>
                    <input class="form-control" id="password_confirmation" placeholder="Confirm Your Password"
                        type="password" name="password_confirmation">
                    @error('password_confirmation')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                {{-- <div class="container-login100-form-btn">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>

                <div class="submit d-flex justify-content-center mt-3">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div> --}}


                <div class="container-login100-form-btn">
                    <button type="submit" class="login100-form-btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
@endsection
