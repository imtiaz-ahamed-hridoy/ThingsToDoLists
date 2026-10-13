@component('mail::message')
# Your Verification Has Been Approved

Hello {{ $user->name }}!

Congratulations! Your NID card and face verification have been successfully approved.

You now have access to all the benefits of your verified account.


Thank you for verifying your identity with us. We appreciate your effort in completing the verification process.

Thanks,
{{ config('app.name') }}
@endcomponent
