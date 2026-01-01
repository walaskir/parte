<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Oznámení úmrtí - {{ $first_name }} {{ $last_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border: 2px solid #333;
            max-width: 600px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            font-size: 28px;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .info {
            margin: 20px 0;
            line-height: 1.8;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>PARTE</h1>

        <div class="info">
            <div><span class="label">Jméno:</span> {{ $first_name }} {{ $last_name }}</div>
            @if(!empty($funeral_date))
            <div><span class="label">Datum pohřbu:</span> {{ \Carbon\Carbon::parse($funeral_date)->format('d. m. Y') }}</div>
            @endif
            <div><span class="label">Zdroj:</span> {{ $source }}</div>
        </div>

        <div class="footer">
            Staženo: {{ now()->format('d. m. Y H:i') }}<br>
            Hash: {{ $hash ?? 'N/A' }}
        </div>
    </div>
</body>
</html>
