<?php

namespace App\Imports;

use App\Models\PartNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PartNumberImport implements ToArray, WithHeadingRow
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
                $description = isset($row['description']) && trim((string) $row['description']) !== ''
                    ? trim((string) $row['description'])
                    : null;

                // Skip if entire row is empty
                if ($pnBaan === '' && $description === null) {
                    continue;
                }

                if ($pnBaan === '') {
                    $this->errorCount++;
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'field' => 'pn_baan',
                        'value' => '-',
                        'message' => "Baris {$rowNumber}: Kolom 'pn_baan' wajib diisi.",
                    ];
                    continue;
                }

                $partNumber = PartNumber::withTrashed()->where('pn_baan', $pnBaan)->first();

                if ($partNumber) {
                    if ($description !== null) {
                        $partNumber->description = $description;
                    }
                    $partNumber->deleted_at = null;
                    $partNumber->save();

                    $this->updatedCount++;
                    $this->successCount++;
                } else {
                    PartNumber::create([
                        'id' => (string) Str::ulid(),
                        'pn_baan' => $pnBaan,
                        'description' => $description,
                    ]);

                    $this->successCount++;
                }
            }
        });
    }

    public function headingRow(): int
    {
        return 1;
    }
}
