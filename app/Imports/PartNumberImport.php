<?php

namespace App\Imports;

use App\Models\PartNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PartNumberImport implements ToArray, WithHeadingRow
{
    public int $successCount = 0;
    public int $updatedCount = 0;
    public int $errorCount = 0;
    public array $errors = [];

    /**
     * @param array $array
     */
    public function array(array $array): void
    {
        DB::transaction(function () use ($array) {
            foreach ($array as $index => $row) {
                $rowNumber = $index + 2;

                $pnBaan = isset($row['pn_baan']) ? trim((string) $row['pn_baan']) : '';
                $description = isset($row['description']) && trim((string) $row['description']) !== ''
                    ? trim((string) $row['description'])
                    : null;
                $pricePerUnit = isset($row['price_per_unit']) && trim((string) $row['price_per_unit']) !== ''
                    ? (float) str_replace([',', ' '], '', (string) $row['price_per_unit'])
                    : null;

                // Skip if entire row is empty
                if ($pnBaan === '' && $description === null && $pricePerUnit === null) {
                    continue;
                }

                if ($pnBaan === '') {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'pn_baan' wajib diisi.";
                    continue;
                }

                if ($pricePerUnit !== null && $pricePerUnit < 0) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'price_per_unit' tidak boleh bernilai negatif.";
                    continue;
                }

                $partNumber = PartNumber::withTrashed()->where('pn_baan', $pnBaan)->first();

                if ($partNumber) {
                    if ($description !== null) {
                        $partNumber->description = $description;
                    }
                    if ($pricePerUnit !== null) {
                        $partNumber->price_per_unit = $pricePerUnit;
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
                        'price_per_unit' => $pricePerUnit,
                    ]);

                    $this->successCount++;
                }
            }
        });
    }
}

