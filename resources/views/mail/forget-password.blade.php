<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Verification Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            color: #333;
        }
        .content {
            font-size: 16px;
            line-height: 1.6;
            color: #333;
        }
        .verification-code {
            font-size: 24px;
            font-weight: bold;
            color: #3490dc;
            text-align: center;
            margin: 20px 0;
        }
        .footer {
            margin-top: 20px;
            font-size: 14px;
            text-align: center;
            color: #777777;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Password Reset Request</h2>
        <p>{{$systemSettings?->system_name ?? config('app.name')}}</p>
    </div>
    <div class="content">
        <p>Hello {{ $user->name ?? 'there' }},</p>

        <p>We received a request to reset the password for your account. Please use the verification code below to proceed with resetting your password:</p>

        <div class="verification-code">{{ $otp }}</div>

        <p>If you did not request a password reset, please ignore this email.</p>

        <p>Thank you,<br>{{$systemSettings?->system_name ?? config('app.name')}} Team</p>
    </div>
    <div class="footer">
{{--        @if($systemSettings->address)--}}
            <p>{{ $systemSettings?->address ?? "N/A"}}</p>
{{--        @endif--}}
        <p>© {{ date('Y') }} {{$systemSettings?->system_name ?? config('app.name')}}. All rights reserved.</p>
    </div>
</div>
</body>
</html>
