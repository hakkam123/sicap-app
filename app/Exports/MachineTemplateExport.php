<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MachineTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'area_code',
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
                'MC-FA-01',
                'Mesin Assembly 1',
                'Mesin perakitan FA line 1',
            ],
            [
                'SMT',
                'MC-SMT-01',
                'Mesin Pick and Place',
                'Mesin penempatan komponen SMT line 1',
            ],
        ];
    }
}

