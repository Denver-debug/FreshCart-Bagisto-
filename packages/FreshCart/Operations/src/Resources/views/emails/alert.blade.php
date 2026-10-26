<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #111;">
    <p style="font-size: 16px; font-weight: bold;">{{ $alertTitle }}</p>

    @if ($alertBody)
        <p>{{ $alertBody }}</p>
    @endif

    @if ($alertUrl)
        <p><a href="{{ $alertUrl }}">{{ $alertUrl }}</a></p>
    @endif

    <p style="color: #666; font-size: 12px;">FreshCart Operations</p>
</body>
</html>
