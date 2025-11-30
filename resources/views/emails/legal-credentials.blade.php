<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Solicitor access</title>
</head>
<body>
<p>Hello {{ $legal->name ?? 'Solicitor' }},</p>
<p>Your solicitor workspace credentials have been refreshed. Sign in with the details below to continue managing your assigned cases.</p>
<ul>
    <li><strong>Login URL:</strong> <a href="{{ $loginUrl }}">{{ $loginUrl }}</a></li>
    <li><strong>Email:</strong> {{ $legal->email }}</li>
    <li><strong>Password:</strong> {{ $password }}</li>
</ul>
<p>For security, please update your password after your next login.</p>
<p>Thank you,<br>HomeTrans CRM Team</p>
</body>
</html>
