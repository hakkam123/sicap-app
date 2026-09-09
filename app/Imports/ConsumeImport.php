<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ConsumeImport implements ToArray, WithHeadingRow
{
    public int $successCount = 0;
    public int $errorCount = 0;
    public array $errors = [];

    /**
     * @param array $array
     */
    public function array(array $array): void
    {
        $userId = Auth::id();

        DB::transaction(function () use ($array, $userId) {
            foreach ($array as $index => $row) {
                $rowNumber = $index + 2;

                $pnBaan = isset($row['pn_baan']) ? trim((string) $row['pn_baan']) : '';
                $areaCode = isset($row['area_code']) ? trim((string) $row['area_code']) : '';
                $machineCode = isset($row['machine_code']) ? trim((string) $row['machine_code']) : '';
                $qtyRaw = isset($row['qty']) ? trim((string) $row['qty']) : (isset($row['quantity']) ? trim((string) $row['quantity']) : '');
                $consumedAtRaw = isset($row['consumed_at']) ? $row['consumed_at'] : null;

                // Skip completely empty row
                if ($pnBaan === '' && $areaCode === '' && $machineCode === '' && $qtyRaw === '' && empty($consumedAtRaw)) {
                    continue;
                }

                if ($pnBaan === '') {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'pn_baan' wajib diisi.";
                    continue;
                }

                $partNumber = PartNumber::where('pn_baan', $pnBaan)->first();
                if (!$partNumber) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Part Number '{$pnBaan}' tidak ditemukan.";
                    continue;
                }

                if ($qtyRaw === '' || !is_numeric($qtyRaw) || (int) $qtyRaw <= 0) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'qty' harus berupa angka bulat positif lebih dari 0.";
                    continue;
                }
                $qty = (int) $qtyRaw;

                // Parse consumed_at
                $consumedAt = null;
                if (!empty($consumedAtRaw)) {
                    try {
                        if (is_numeric($consumedAtRaw)) {
                            $consumedAt = Carbon::instance(ExcelDate::excelToDateTimeObject($consumedAtRaw));
                        } elseif ($consumedAtRaw instanceof DateTimeInterface) {
                            $consumedAt = Carbon::instance($consumedAtRaw);
                        } else {
                            $consumedAt = Carbon::parse($consumedAtRaw);
                        }
                    } catch (\Throwable $e) {
                        $this->errorCount++;
                        $this->errors[] = "Baris {$rowNumber}: Format tanggal '{$consumedAtRaw}' tidak valid.";
                        continue;
                    }
                } else {
                    $consumedAt = now();
                }

                // Area lookup
                $areaId = null;
                if ($areaCode !== '') {
                    $area = Area::where('code', $areaCode)->first();
                    if (!$area) {
                        $this->errorCount++;
                        $this->errors[] = "Baris {$rowNumber}: Area dengan kode '{$areaCode}' tidak ditemukan.";
                        continue;
                    }
                    $areaId = $area->id;
                }

                // Machine lookup
                $machineId = null;
                if ($machineCode !== '') {
                    $machineQuery = Machine::where('code', $machineCode);
                    if ($areaId) {
                        $machine = (clone $machineQuery)->where('area_id', $areaId)->first() ?: $machineQuery->first();
                    } else {
                        $machine = $machineQuery->first();
                    }

                    if (!$machine) {
                        $this->errorCount++;
                        $this->errors[] = "Baris {$rowNumber}: Machine dengan kode '{$machineCode}' tidak ditemukan.";
                        continue;
                    }
                    $machineId = $machine->id;
                    if (!$areaId && $machine->area_id) {
                        $areaId = $machine->area_id;
                    }
                }

                $amount = null;
                if ($partNumber->price_per_unit !== null) {
                    $amount = round($qty * (float) $partNumber->price_per_unit, 2);
                }

                Consume::create([
                    'id' => (string) Str::ulid(),
                    'part_number_id' => $partNumber->id,
                    'area_id' => $areaId,
                    'machine_id' => $machineId,
                    'quantity' => $qty,
                    'amount' => $amount,
                    'consumed_at' => $consumedAt,
                    'source' => 'import_excel',
                    'created_by' => $userId,
                ]);

                $this->successCount++;
            }
        });
    }

    /**
     * @return int
     */
    public function headingRow(): int
    {
        return 1;
    }
}
