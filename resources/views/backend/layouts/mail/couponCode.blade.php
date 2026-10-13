<!DOCTYPE html>
<html>
<head>
    <title>Your Coupon Code</title>
</head>
<body>
    <h2>Hello!</h2>
    <p>Here are your codes:</p>

    @foreach ($codes as $item)
        <div style="margin-bottom: 20px;">
            <strong>Coupon:</strong> {{ $item['original'] }} <br>
        </div>
        <hr>
    @endforeach

    <p>Click the link or scan the QR code to get your coupon card.</p>
    <p>These codes will expire in 365 days.</p>
    <p>Best Regards,</p>
    <p>Grandsave</p>
</body>
</html>
