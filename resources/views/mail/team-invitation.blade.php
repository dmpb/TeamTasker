<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Team invitation') }}</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
    <p>{{ __('Hello,') }}</p>
    <p>
        {{ __(':inviter invited you to join the team “:team” with role :role.', [
            'inviter' => $inviterName,
            'team' => $teamName,
            'role' => $role,
        ]) }}
    </p>
    <p>
        <a href="{{ $acceptUrl }}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px;">
            {{ __('Open invitation') }}
        </a>
    </p>
    <p style="font-size: 12px; color: #666;">
        {{ __('If you did not expect this email, you can ignore it.') }}
    </p>
</body>
</html>
