<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ConsumeImport implements ToArray, WithHeadingRow
{
    /**
     * @param array $array
     * @return array
     */
    public function array(array $array): void
    {
        //
    }

    /**
     * @return int
     */
    public function headingRow(): int
    {
        return 1;
    }
}
