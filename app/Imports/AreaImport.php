<?php

namespace App\Imports;

use App\Models\Area;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AreaImport implements ToArray, WithHeadingRow
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

                $code = isset($row['code']) ? trim((string) $row['code']) : '';
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                $description = isset($row['description']) && trim((string) $row['description']) !== ''
                    ? trim((string) $row['description'])
                    : null;

                // Skip if entire row is empty
                if ($code === '' && $name === '' && $description === null) {
                    continue;
                }

                if ($code === '') {
                    $this->errorCount++;
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'field' => 'code',
                        'value' => '-',
                        'message' => "Baris {$rowNumber}: Kolom 'code' wajib diisi.",
                    ];
                    continue;
                }

                if ($name === '') {
                    $this->errorCount++;
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'field' => 'name',
                        'value' => '-',
                        'message' => "Baris {$rowNumber}: Kolom 'name' wajib diisi.",
                    ];
                    continue;
                }

                $area = Area::withTrashed()->where('code', $code)->first();

                if ($area) {
                    $area->name = $name;
                    $area->description = $description;
                    $area->deleted_at = null;
                    $area->save();

                    $this->updatedCount++;
                    $this->successCount++;
                } else {
                    Area::create([
                        'id' => (string) Str::ulid(),
                        'code' => $code,
                        'name' => $name,
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
