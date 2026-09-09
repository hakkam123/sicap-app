<?php

namespace App\Exports;

use App\Models\Consume;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConsumeExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
            'partNumber:id,pn_baan,description,price_per_unit',
            'area:id,code,name',
            'machine:id,code,name',
            'creator:id,name',
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
            'Tanggal',
            'Part Number (PN BAAN)',
            'Deskripsi Part',
            'Area',
            'Mesin / Station',
            'Qty',
            'Nilai Pemakaian (IDR)',
            'Sumber Input',
            'Diinput Oleh',
        ];
    }

    /**
     * @param Consume $consume
     */
    public function map($consume): array
    {
        $this->rowNumber++;

        $sourceLabel = match ($consume->source) {
            'import_excel' => 'Import Excel',
            'api'          => 'API Sync',
            default        => 'Manual',
        };

        return [
            $this->rowNumber,
            $consume->consumed_at ? $consume->consumed_at->format('d/m/Y') : '-',
            $consume->partNumber?->pn_baan ?? '-',
            $consume->partNumber?->description ?? '-',
            $consume->area?->name ?? 'Tidak Diketahui',
            $consume->machine?->name ?? '-',
            abs($consume->quantity),
            abs($consume->amount ?? 0),
            $sourceLabel,
            $consume->creator?->name ?? '-',
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
                    'startColor' => ['rgb' => '1E293B'],
                ],
            ],
        ];
    }
}
