<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Konsumsi Sparepart</title>
    <style>
        @page {
            margin: 20px 25px;
            size: A4 landscape;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.3;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .header-subtitle {
            font-size: 11px;
            font-weight: bold;
            color: #475569;
            margin-top: 2px;
        }
        .header-meta {
            text-align: right;
            font-size: 9px;
            color: #64748b;
        }
        .filter-badges {
            margin-bottom: 12px;
            padding: 6px 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 9px;
            color: #334155;
        }
        .filter-item {
            display: inline-block;
            margin-right: 15px;
        }
        .filter-label {
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        table.data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #0f172a;
        }
        table.data-table td {
            padding: 5px 8px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: "Courier New", Courier, monospace; }
        .font-bold { font-weight: bold; }
        .total-row td {
            background-color: #e2e8f0 !important;
            font-weight: bold;
            border-top: 2px solid #0f172a;
            font-size: 10px;
        }
        .footer {
            margin-top: 15px;
            font-size: 8px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="header-title">PT ASTRA VISTEON INDONESIA</div>
                    <div class="header-subtitle">SICAP — LAPORAN KONSUMSI SPAREPART</div>
                </td>
                <td class="header-meta">
                    <div>Dicetak pada: {{ now()->translatedFormat('d F Y H:i') }} WIB</div>
                    <div>Total Transaksi: {{ number_format($consumptions->count(), 0, ',', '.') }} baris</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Filter Meta -->
    <div class="filter-badges">
        <span class="filter-item">
            <span class="filter-label">Periode:</span> 
            {{ $filters['date_from'] ?? 'Awal' }} s/d {{ $filters['date_to'] ?? 'Sekarang' }}
        </span>
        <span class="filter-item">
            <span class="filter-label">Area:</span> 
            {{ $areaName ?? 'Semua Area' }}
        </span>
        <span class="filter-item">
            <span class="filter-label">Mesin:</span> 
            {{ $machineName ?? 'Semua Mesin' }}
        </span>
        @if(!empty($filters['search']))
        <span class="filter-item">
            <span class="filter-label">Pencarian:</span> 
            "{{ $filters['search'] }}"
        </span>
        @endif
    </div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">No</th>
                <th style="width: 80px;">Tanggal</th>
                <th style="width: 140px;">PN BAAN</th>
                <th>Deskripsi</th>
                <th style="width: 100px;">Area</th>
                <th style="width: 120px;">Machine</th>
                <th class="text-right" style="width: 50px;">Qty</th>
                <th class="text-right" style="width: 110px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($consumptions as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $item->consumed_at ? $item->consumed_at->format('d/m/Y') : '-' }}</td>
                <td class="font-mono font-bold">{{ $item->partNumber?->pn_baan ?? '-' }}</td>
                <td>{{ $item->partNumber?->description ?? '-' }}</td>
                <td>{{ $item->area?->name ?? 'Tidak Diketahui' }}</td>
                <td>{{ $item->machine?->name ?? '-' }}</td>
                <td class="text-right font-bold">{{ number_format(abs($item->quantity), 0, ',', '.') }}</td>
                <td class="text-right font-mono">Rp {{ number_format(abs($item->amount ?? 0), 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 20px; color: #94a3b8;">
                    Tidak ada data konsumsi yang sesuai dengan filter.
                </td>
            </tr>
            @endforelse
        </tbody>
        @if($consumptions->count() > 0)
        <tfoot>
            <tr class="total-row">
                <td colspan="6" class="text-right">TOTAL</td>
                <td class="text-right">{{ number_format($totalQty, 0, ',', '.') }}</td>
                <td class="text-right font-mono">Rp {{ number_format($totalAmount, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">
        Dokumen ini dihasilkan secara otomatis oleh Sistem Informasi Consume Sparepart (SICAP).
    </div>
</body>
</html>

