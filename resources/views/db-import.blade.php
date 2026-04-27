<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Import</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f6f7f9; }
        .card { max-width: 680px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; }
        .msg { padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; }
        .ok { background: #ecfdf3; color: #166534; border: 1px solid #86efac; }
        .err { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .warn { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }
        .report { margin-top: 1rem; border-collapse: collapse; width: 100%; }
        .report th, .report td { border: 1px solid #e5e7eb; padding: 0.5rem; text-align: left; }
        .report th { background: #f9fafb; }
        button { margin-top: 1rem; padding: 0.6rem 1rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Temporary DB Import</h2>
        <p class="warn">
            This endpoint is intentionally unsecured for one-time import. Disable/remove it immediately after use.
        </p>

        @if (session('success'))
            <div class="msg ok">{{ session('success') }}</div>
        @endif

        @if (session('import_report'))
            @php($report = session('import_report'))
            <div class="msg ok">
                <strong>Import report</strong><br>
                File: {{ $report['file_name'] ?? 'unknown' }}<br>
                Imported at: {{ $report['imported_at'] ?? 'unknown' }}
            </div>

            <table class="report">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Rows after import</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (($report['table_counts'] ?? []) as $table => $count)
                        <tr>
                            <td>{{ $table }}</td>
                            <td>{{ is_null($count) ? 'missing table' : $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (session('error'))
            <div class="msg err">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="msg err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('db-import.run') }}" enctype="multipart/form-data">
            @csrf
            <label for="sql_file">SQL file (.sql)</label><br>
            <input id="sql_file" type="file" name="sql_file" accept=".sql,.txt" required>
            <br>
            <button type="submit">Upload and Import</button>
        </form>
    </div>
</body>
</html>
