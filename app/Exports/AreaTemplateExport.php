<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AreaTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'code',
            'name',
            'description',
        ];
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return [
            [
                'FA',
                'Final Assembly',
                'Area perakitan akhir',
            ],
            [
                'SMT',
                'Surface Mount Technology',
                'Area pemasangan komponen elektronik',
            ],
        ];
    }
}

