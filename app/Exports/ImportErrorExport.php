<?php

namespace App\Exports;

use App\Models\ImportLog;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportErrorExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected ImportLog $importLog
    ) {}

    public function array(): array
    {
        $rawErrors = $this->importLog->error_details ?? [];

        if (is_string($rawErrors)) {
            $decoded = json_decode($rawErrors, true);
            $rawErrors = is_array($decoded) ? $decoded : [$rawErrors];
        }

        if (empty($rawErrors) && !empty($this->importLog->error_message)) {
            $rawErrors = explode("\n", $this->importLog->error_message);
        }

        $rows = [];
        $no = 1;

        foreach ($rawErrors as $err) {
            if (is_array($err)) {
                $rows[] = [
                    'no' => $no++,
                    'row' => $err['row'] ?? '-',
                    'field' => $err['field'] ?? 'General',
                    'value' => isset($err['value']) ? (is_scalar($err['value']) ? (string)$err['value'] : json_encode($err['value'])) : '-',
                    'message' => $err['message'] ?? (is_string($err) ? $err : json_encode($err)),
                ];
            } else {
                $str = (string) $err;
                $rowNum = '-';
                $field = 'General';
                $msg = $str;
                $val = '-';

                if (preg_match('/^Baris\s+(\d+):\s*(.+)$/i', $str, $matches)) {
                    $rowNum = $matches[1];
                    $msg = $matches[2];
                }

                $rows[] = [
                    'no' => $no++,
                    'row' => $rowNum,
                    'field' => $field,
                    'value' => $val,
                    'message' => $msg,
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'Baris Excel',
            'Kolom / Field',
            'Nilai Bermasalah',
            'Pesan Kesalahan',
        ];
    }

    public function title(): string
    {
        return 'Laporan Error Import';
    }

    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFDC2626'], // Red header
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            'A1:E' . $highestRow => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ],
            'A2:A' . $highestRow => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'B2:B' . $highestRow => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
