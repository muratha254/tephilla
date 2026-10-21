<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $payload['title'] }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333; line-height:1.5;">
    <p>{!! nl2br(e($payload['message'])) !!}</p>
</body>
</html>
