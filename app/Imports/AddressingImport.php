<?php

namespace App\Imports;

use App\Models\PartNumber;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AddressingImport implements ToArray, WithHeadingRow
{
    public int $totalCount = 0;
    public int $successCount = 0;
    public int $updatedCount = 0;
    public int $errorCount = 0;
    public array $errors = [];

    /**
     * @param array $array
     */
    public function array(array $array): void
    {
        $this->totalCount = count($array);

        DB::transaction(function () use ($array) {
            foreach ($array as $index => $row) {
                $rowNumber = $index + 2;

                $pnBaan = isset($row['pn_baan']) ? trim((string) $row['pn_baan']) : '';
                $partNumberCode = isset($row['part_number_code']) && trim((string) $row['part_number_code']) !== ''
                    ? trim((string) $row['part_number_code'])
                    : null;
                $addressing = isset($row['addressing']) && trim((string) $row['addressing']) !== ''
                    ? trim((string) $row['addressing'])
                    : null;

                // Skip completely empty row
                if ($pnBaan === '' && $partNumberCode === null && $addressing === null) {
                    continue;
                }

                if ($pnBaan === '') {
                    $this->errorCount++;
                    $this->errors[] = [
                        'row'     => $rowNumber,
                        'field'   => 'pn_baan',
                        'value'   => '-',
                        'message' => "Baris {$rowNumber}: Kolom 'pn_baan' wajib diisi.",
                    ];
                    continue;
                }

                $partNumber = PartNumber::where('pn_baan', $pnBaan)->first();
                if (!$partNumber) {
                    $this->errorCount++;
                    $this->errors[] = [
                        'row'     => $rowNumber,
                        'field'   => 'pn_baan',
                        'value'   => $pnBaan,
                        'message' => "Baris {$rowNumber}: Part Number '{$pnBaan}' tidak ditemukan di database.",
                    ];
                    continue;
                }

                $partNumber->part_number_code = $partNumberCode;
                $partNumber->addressing = $addressing;
                $partNumber->save();

                $this->updatedCount++;
                $this->successCount++;
            }
        });
    }

    public function headingRow(): int
    {
        return 1;
    }
}
