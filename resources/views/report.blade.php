<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EgovService — Request Report</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h2>Request Statistics</h2>
    <table>
        <thead>
            <tr>
                <th>Year</th>
                <th>Month</th>
                <th>Personal Requests</th>
                <th>Employment Requests</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $row)
            <tr>
                <td>{{ $row->request_year }}</td>
                <td>{{ $row->request_month }}</td>
                <td>{{ $row->personal_requests }}</td>
                <td>{{ $row->employment_requests }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
