<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PartNumberTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'pn_baan',
            'description',
            'price_per_unit',
        ];
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return [
            [
                'PN-001',
                'Bearing 6205',
                45000,
            ],
            [
                'PN-002',
                'Solenoid Valve 24V',
                125000,
            ],
        ];
    }
}

