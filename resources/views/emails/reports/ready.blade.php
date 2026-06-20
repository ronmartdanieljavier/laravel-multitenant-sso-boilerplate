<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Report is Ready</title>
</head>
<body>
    <h1>Your Report is Ready</h1>
    <p>Hello,</p>
    <p>Your report of type <strong>{{ $report->type }}</strong> has been generated successfully.</p>
    <p>
        Report details:<br>
        Format: {{ $report->format->value }}<br>
        Completed: {{ $report->completed_at?->toDateTimeString() }}
    </p>
    <p>You can download it by logging in to the application.</p>
</body>
</html>
