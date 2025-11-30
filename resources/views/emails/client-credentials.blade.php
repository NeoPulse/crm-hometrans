<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client access</title>
</head>
<body>
<p>Hello {{ $client->name ?? 'Client' }},</p>
<p>Your client portal access has been prepared. Use the credentials below to sign in and review your cases.</p>
<ul>
    <li><strong>Login URL:</strong> <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></li>
    <li><strong>Email:</strong> {{ $client->email }}</li>
    <li><strong>Password:</strong> {{ $password }}</li>
</ul>
<p>Please keep these details secure and change your password after signing in.</p>
<p>Best regards,<br>HomeTrans CRM Team</p>
</body>
</html>
