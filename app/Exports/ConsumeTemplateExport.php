<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ConsumeTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Date',
            'Part Number',
            'Desc',
            'qty',
            'Amount',
        ];
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return [
            [
                '1 juli 2026',
                'SPFAMEBITHOL-2295',
                'Bit Holder 1736234 (Contoh)',
                -1,
                -3100000,
            ],
            [
                '2 juli 2026',
                'SPFAMEBITHOL-2295',
                'Bit Holder 1736234 (Contoh)',
                -2,
                -6200000,
            ],
        ];
    }
}

