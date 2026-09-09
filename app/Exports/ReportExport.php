<?php

namespace App\Exports;

use App\Models\Consume;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting
{
    protected array $filters;
    private int $rowNumber = 0;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $query = Consume::query()->with([
            'partNumber:id,pn_baan,description',
            'area:id,code,name',
            'machine:id,code,name',
        ]);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->whereHas('partNumber', function ($q) use ($search) {
                $q->where('pn_baan', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['area_id'])) {
            $query->where('area_id', $this->filters['area_id']);
        }

        if (!empty($this->filters['machine_id'])) {
            $query->where('machine_id', $this->filters['machine_id']);
        }

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('consumed_at', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->whereDate('consumed_at', '<=', $this->filters['date_to']);
        }

        return $query->orderBy('consumed_at', 'DESC');
    }

    public function headings(): array
    {
        return [
            'No',
            'PN BAAN',
            'Deskripsi',
            'Area',
            'Machine',
            'Qty',
            'Amount',
        ];
    }

    /**
     * @param Consume $consume
     */
    public function map($consume): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $consume->partNumber?->pn_baan ?? '-',
            $consume->partNumber?->description ?? '-',
            $consume->area?->name ?? 'Tidak Diketahui',
            $consume->machine?->name ?? '-',
            abs($consume->quantity),
            (float) abs($consume->amount ?? 0),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER,
            'G' => '"Rp "#,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F172A'],
                ],
            ],
        ];
    }
}

