<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

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
                '6 Juli 2026',
                'SPSMMEFILTER-3747',
                'Filter NXT H02 (AA8BD00)',
                -4,
                '-1.386.000',
            ],
            [
                '7 Juli 2026',
                'SPSMMEFILTER-3748',
                'Filter NXT H03 (AA8BD01)',
                10,
                '2.500.000',
            ],
        ];
    }
}
