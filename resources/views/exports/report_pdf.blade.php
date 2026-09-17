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
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .header-logo-cell {
            width: 25%;
            vertical-align: middle;
            text-align: left;
        }
        .header-logo {
            max-height: 44px;
            max-width: 170px;
        }
        .header-title-cell {
            width: 75%;
            vertical-align: middle;
            text-align: center;
            padding-right: 18%; /* Counter-balance the logo to center the text */
        }
        .header-title {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
            color: #000000;
            letter-spacing: 0.2px;
        }
        .header-divider {
            width: 100%;
            height: 2px;
            background-color: #000000;
            margin-bottom: 10px;
        }
        .meta-line {
            font-size: 8.5px;
            font-weight: normal;
            color: #1e293b;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .meta-item {
            margin-right: 18px;
            display: inline-block;
        }
        .meta-label {
            font-weight: bold;
            color: #0f172a;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.data-table th {
            background-color: #0a192f;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 7px;
            border: 1px solid #0a192f;
            letter-spacing: 0.3px;
        }
        table.data-table td {
            padding: 5.5px 7px;
            border: 1px solid #cbd5e1;
            font-size: 8px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-mono { font-family: "Courier New", Courier, monospace; }
        .font-bold { font-weight: bold; }
        .whitespace-nowrap { white-space: nowrap; }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .footer-meta {
            text-align: right;
            font-size: 8.5px;
            color: #1e293b;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="header-logo" alt="Astra Visteon Indonesia" />
                @else
                    <div style="font-size: 13px; font-weight: bold; color: #000;">Astra Visteon INDONESIA</div>
                @endif
            </td>
            <td class="header-title-cell">
                <div class="header-title">Laporan Konsumsi Sparepart {{ $reportYear }}</div>
            </td>
        </tr>
    </table>

    <div class="header-divider"></div>

    <!-- Metadata / Filter Bar -->
    <div class="meta-line">
        <span class="meta-item"><span class="meta-label">PERIODE:</span> {{ $periodText }}</span>
        <span class="meta-item"><span class="meta-label">AREA:</span> {{ $areaName ?? 'Semua Area' }}</span>
        <span class="meta-item"><span class="meta-label">MESIN:</span> {{ $machineName ?? 'Semua Mesin' }}</span>
        @if(!empty($filters['search']))
            <span class="meta-item"><span class="meta-label">PENCARIAN:</span> "{{ $filters['search'] }}"</span>
        @endif
    </div>

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 25px;">NO</th>
                <th class="text-center" style="width: 65px;">TANGGAL</th>
                <th class="text-left" style="width: 105px;">PN BAAN</th>
                <th class="text-left">DESKRIPSI</th>
                <th class="text-left" style="width: 90px;">AREA</th>
                <th class="text-left" style="width: 135px;">MACHINE</th>
                <th class="text-center" style="width: 35px;">QTY</th>
                <th class="text-right" style="width: 85px;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($consumptions as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center whitespace-nowrap">{{ $item->consumed_at ? $item->consumed_at->format('d/m/Y') : '-' }}</td>
                <td class="font-mono font-bold">{{ $item->partNumber?->pn_baan ?? '-' }}</td>
                <td>{{ $item->partNumber?->description ?? '-' }}</td>
                <td>{{ $item->display_area }}</td>
                <td>{{ $item->display_machine }}</td>
                <td class="text-center font-bold">{{ number_format(abs($item->quantity), 0, ',', '.') }}</td>
                <td class="text-right font-mono whitespace-nowrap">Rp {{ number_format(abs($item->amount ?? 0), 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 18px; color: #94a3b8;">
                    Tidak ada data konsumsi yang sesuai dengan filter.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer Meta -->
    <table class="footer-table">
        <tr>
            <td class="footer-meta">
                Total {{ number_format($consumptions->count(), 0, ',', '.') }} Transaksi, Dicetak pada: {{ now()->translatedFormat('d F Y H:i') }} WIB
            </td>
        </tr>
    </table>
</body>
</html>
