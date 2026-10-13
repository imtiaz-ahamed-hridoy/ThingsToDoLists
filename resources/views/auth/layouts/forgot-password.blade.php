@extends('auth.app')

@section('title', 'Forgot Password')

@section('content')
    <div class="wrap-login100 p-0">
        <div class="card-body">
            <form class="card shadow-none" action="{{ route('password.email') }}" method="post">
                @csrf
                <div class="card-body">
                    <div class="text-center">
                        <span class="login100-form-title">
                            Forgot Password
                        </span>
                        <p class="text-muted">Enter the email address registered on your account</p>
                    </div>
                    <div class="pt-3" id="forgot">
                        <div class="form-group">
                            <label class="form-label" for="eMail">E-Mail:</label>
                            <input class="form-control" id="eMail" placeholder="Enter Your Email" type="email"
                                name="email" value="{{ old('email') }}">
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="container-login100-form-btn">
                            <button type="submit" class="login100-form-btn btn-primary">Find</button>
                        </div>
                        <div class="text-center mt-4">
                            <p class="text-dark mb-0">Forgot It?<a class="text-primary ms-1"
                                    href="{{ route('login') }}">Send me Back</a></p>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>
@endsection
