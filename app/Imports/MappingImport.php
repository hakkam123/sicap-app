<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MappingImport implements ToArray, WithHeadingRow
{
    public int $successCount = 0;
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
                $areaCode = isset($row['area_code']) ? trim((string) $row['area_code']) : '';
                $machineCode = isset($row['machine_code']) ? trim((string) $row['machine_code']) : '';

                if (empty($pnBaan)) {
                    continue;
                }

                $partNumber = PartNumber::where('pn_baan', $pnBaan)->first();
                if (!$partNumber) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Part Number '{$pnBaan}' tidak ditemukan.";
                    continue;
                }

                if (!empty($areaCode)) {
                    $area = Area::where('code', $areaCode)->first();
                    if (!$area) {
                        $this->errorCount++;
                        $this->errors[] = "Baris {$rowNumber}: Area dengan kode '{$areaCode}' tidak ditemukan.";
                        continue;
                    }

                    // Attach area to part without detaching existing ones
                    $partNumber->areas()->syncWithoutDetaching([$area->id]);

                    if (!empty($machineCode)) {
                        $machine = Machine::where('code', $machineCode)
                            ->where('area_id', $area->id)
                            ->first();

                        if (!$machine) {
                            // Fallback search by code only
                            $machine = Machine::where('code', $machineCode)->first();
                        }

                        if ($machine) {
                            $partNumber->machines()->syncWithoutDetaching([$machine->id]);
                        } else {
                            $this->errorCount++;
                            $this->errors[] = "Baris {$rowNumber}: Machine '{$machineCode}' tidak ditemukan di area '{$areaCode}'.";
                            continue;
                        }
                    }
                }

                $this->successCount++;
            }
        });
    }
}

