<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Form Submission</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="margin: 20px 0;">
        <div style="background-color: #fff; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px; white-space: pre-wrap;">{{ $messageContent }}</div>
    </div>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

    <p style="color: #9ca3af; font-size: 12px;">
        This message was sent via the contact form on {{ config('app.name') }}.
    </p>
</body>
</html>
