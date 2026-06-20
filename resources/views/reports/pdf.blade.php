<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #111; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .meta { color: #666; font-size: 11px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { background: #f3f4f6; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
        td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
        .empty { color: #999; font-style: italic; margin-top: 24px; }
    </style>
</head>
<body>
    <h1>{{ ucwords(str_replace('_', ' ', $report->type)) }} Report</h1>
    <p class="meta">
        Generated: {{ $generatedAt }}<br>
        @if($report->parameters)
            Parameters: {{ collect($report->parameters)->map(fn($v, $k) => "{$k}: {$v}")->implode(' | ') }}
        @endif
    </p>

    @if(!empty($rows))
        <table>
            <thead>
                <tr>
                    @foreach(array_keys($rows[0]) as $col)
                        <th>{{ ucwords(str_replace('_', ' ', $col)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No data available for this report.</p>
    @endif
</body>
</html>
