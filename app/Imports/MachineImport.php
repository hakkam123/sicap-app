<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Machine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MachineImport implements ToArray, WithHeadingRow
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

                $areaCode = isset($row['area_code']) ? trim((string) $row['area_code']) : '';
                $code = isset($row['code']) ? trim((string) $row['code']) : '';
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                $description = isset($row['description']) && trim((string) $row['description']) !== ''
                    ? trim((string) $row['description'])
                    : null;

                // Skip if entire row is empty
                if ($areaCode === '' && $code === '' && $name === '' && $description === null) {
                    continue;
                }

                if ($areaCode === '') {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'area_code' wajib diisi.";
                    continue;
                }

                if ($code === '') {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'code' wajib diisi.";
                    continue;
                }

                if ($name === '') {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'name' wajib diisi.";
                    continue;
                }

                $area = Area::where('code', $areaCode)->first();
                if (!$area) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Area dengan kode '{$areaCode}' tidak ditemukan di database.";
                    continue;
                }

                $machine = Machine::withTrashed()
                    ->where('area_id', $area->id)
                    ->where('code', $code)
                    ->first();

                if ($machine) {
                    $machine->name = $name;
                    $machine->description = $description;
                    $machine->deleted_at = null;
                    $machine->save();

                    $this->updatedCount++;
                    $this->successCount++;
                } else {
                    Machine::create([
                        'id' => (string) Str::ulid(),
                        'area_id' => $area->id,
                        'code' => $code,
                        'name' => $name,
                        'description' => $description,
                    ]);

                    $this->successCount++;
                }
            }
        });
    }
}

