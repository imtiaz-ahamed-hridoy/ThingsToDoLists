@extends('auth.app')

@section('title', 'Register')

@section('content')
    <div class="wrap-login100 p-0">
        <div class="card-body">
            <form class="login100-form validate-form" action="{{ route('register') }}" method="POST">
                @csrf
                <span class="login100-form-title">Register</span>

                <div class="wrap-input100 validate-input">
                    <input class="input100" type="text" name="name" placeholder="Name" value="{{ old('name') }}" required>
                    <span class="focus-input100"></span>
                    @error('name')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="wrap-input100 validate-input">
                    <input class="input100" type="email" name="email" placeholder="Email" value="{{ old('email') }}" required>
                    <span class="focus-input100"></span>
                    @error('email')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="wrap-input100 validate-input">
                    <input class="input100" type="password" name="password" placeholder="Password" required>
                    <span class="focus-input100"></span>
                    @error('password')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>

                <div class="wrap-input100 validate-input">
                    <input class="input100" type="password" name="password_confirmation" placeholder="Confirm Password" required>
                    <span class="focus-input100"></span>
                </div>

                <div class="container-login100-form-btn">
                    <button type="submit" class="login100-form-btn btn-primary">Register</button>
                </div>
            </form>
        </div>
    </div>
@endsection
