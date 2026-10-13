<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Subscription Successful</title>
</head>
<body>
    <h2>Hello {{ $subscription->user->name ?? 'User' }},</h2>

    <p>Congratulations! Your subscription is now <strong>active</strong>.</p>

    <h3>Subscription Details:</h3>
    <ul>
        <li><strong>Plan Name:</strong> {{ $plan->name ?? 'N/A' }}</li>
        <li><strong>Plan Type:</strong> {{ $plan->type ?? 'N/A' }}</li>
        <li><strong>Quantity:</strong> {{ $subscription->quantity }}</li>
        <li><strong>Ends At:</strong> {{ \Carbon\Carbon::parse($subscription->ends_at)->format('F j, Y') }}</li>
    </ul>

    <p>Thank you for choosing our service. We hope you enjoy your subscription!</p>

    <p>Best regards,<br>
    {{ config('app.name') }}</p>
</body>
</html>
