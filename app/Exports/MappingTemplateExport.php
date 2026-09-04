<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MappingTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'pn_baan',
            'area_code',
            'machine_code',
        ];
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return [
            [
                'SPFAMEBITHOL-2295',
                'FA',
                'M_FA_01',
            ],
            [
                'SPFAMEBITHOL-2295',
                'FA',
                'M_FA_02',
            ],
        ];
    }
}
