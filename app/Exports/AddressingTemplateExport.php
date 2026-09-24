<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AddressingTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * Define column headings for addressing import template.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'pn_baan',
            'part_number_code',
            'addressing',
        ];
    }

    /**
     * Provide sample data rows for user guidance.
     *
     * @return array
     */
    public function array(): array
    {
        return [
            [
                'PN-BRG-6205',
                'SP-00123',
                'RACK-01 A1/LEMARI 5-TRAY A/CONTAINER BOX A',
            ],
            [
                'PN-SLV-024V',
                'SP-00456',
                'RACK-02 B3/DRAWER 2',
            ],
        ];
    }
}
