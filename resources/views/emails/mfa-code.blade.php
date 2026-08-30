<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFA Verification Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 3px solid #3490dc;
        }
        .header h1 {
            color: #3490dc;
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px 20px;
        }
        .content h2 {
            color: #2d3748;
            margin-top: 0;
        }
        .code-box {
            background-color: #f7fafc;
            border: 2px dashed #3490dc;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            margin: 25px 0;
        }
        .code {
            font-size: 40px;
            font-weight: bold;
            color: #3490dc;
            letter-spacing: 12px;
            font-family: 'Courier New', monospace;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #718096;
            font-size: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .warning {
            color: #e53e3e;
            font-size: 14px;
            margin-top: 15px;
        }
        .info {
            background-color: #ebf8ff;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .info p {
            margin: 5px 0;
        }
        .expires {
            color: #718096;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 MFA Verification</h1>
        </div>

        <div class="content">
            <h2>Hello {{ $user->name }}!</h2>
            <p>You have requested a Multi-Factor Authentication (MFA) code for your account.</p>
            
            <div class="info">
                <p><strong>📧 Email:</strong> {{ $user->email }}</p>
                <p><strong>⏱️ Expires in:</strong> {{ $expires_in ?? '10 minutes' }}</p>
            </div>

            <div class="code-box">
                <p style="margin: 0; color: #4a5568; font-size: 14px;">Your verification code is:</p>
                <div class="code">{{ $code }}</div>
            </div>

            <p><strong>This code will expire in {{ $expires_in ?? '10 minutes' }}.</strong></p>

            <p class="warning">⚠️ If you did not request this code, please ignore this email and secure your account immediately.</p>

            <p style="margin-top: 25px; color: #4a5568; font-size: 14px;">
                Need help? Contact our support team.
            </p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>