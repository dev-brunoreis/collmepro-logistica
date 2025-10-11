<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Etiquetas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        h1 {
            color: #4F46E5;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4F46E5;
        }
        .info {
            color: #666;
            font-size: 11px;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #4F46E5;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #999;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .status-posted {
            color: #059669;
            font-weight: bold;
        }
        .status-not-posted {
            color: #DC2626;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de Etiquetas</h1>
        <div class="info">Gerado em: {{ $generated_at }}</div>
        <div class="info">Total de registros: {{ $total }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Número do Pedido</th>
                <th>Número da Etiqueta</th>
                <th>Data de Emissão</th>
                <th>Formato</th>
                <th>Status</th>
                <th>Observação</th>
            </tr>
        </thead>
        <tbody>
            @foreach($labels as $label)
            <tr>
                <td>{{ $label->orderSummary ? $label->orderSummary->numero : 'N/A' }}</td>
                <td>{{ $label->tracking_code ?? $label->bling_label_id }}</td>
                <td>{{ $label->issue_date ? $label->issue_date->format('d/m/Y H:i') : 'N/A' }}</td>
                <td>{{ $label->format }}</td>
                <td class="{{ $label->is_posted ? 'status-posted' : 'status-not-posted' }}">
                    {{ $label->status_text }}
                </td>
                <td>{{ $label->observation ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Collmepro Logistics System - Relatório gerado automaticamente</p>
    </div>
</body>
</html>

