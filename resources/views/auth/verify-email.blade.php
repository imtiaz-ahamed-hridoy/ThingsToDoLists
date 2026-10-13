@extends('auth.app')

@section('title', 'Verify Email')

@section('content')
    <div class="wrap-login100 p-0">
        <div class="card-body">
            <div class="login100-form">
                <span class="login100-form-title">Verify Email</span>

                @if (session('status') === 'verification-link-sent')
                    <p class="text-success text-center">A new verification link has been sent.</p>
                @else
                    <p class="text-muted text-center">Please verify your email address before continuing.</p>
                @endif

                <form action="{{ route('verification.send') }}" method="POST">
                    @csrf
                    <div class="container-login100-form-btn">
                        <button type="submit" class="login100-form-btn btn-primary">Resend Verification Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
