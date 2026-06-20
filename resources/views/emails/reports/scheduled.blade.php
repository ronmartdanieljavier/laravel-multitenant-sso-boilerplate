<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Scheduled Report: {{ $report->type }}</title>
</head>
<body>
    <h1>Scheduled Report Delivery</h1>
    <p>Your scheduled report has been generated and is attached to this email.</p>
    <p>
        Report details:<br>
        Type: <strong>{{ $report->type }}</strong><br>
        Format: {{ strtoupper($report->format->value) }}<br>
        Generated: {{ $report->completed_at?->toDateTimeString() }}
    </p>
</body>
</html>
