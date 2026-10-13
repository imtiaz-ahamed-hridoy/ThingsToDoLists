@component('mail::message')
# Password Reset Request

You have requested to reset your password. Your password reset code is:

@component('mail::panel')
**{{ $resetCode }}**
@endcomponent

This code will expire in 5 minute.

If you did not request a password reset, please ignore this email.

Thanks,  
{{ config('app.name') }}
@endcomponent
