<?php

namespace App\Jobs;

use App\Imports\ConsumeImport;
use App\Models\Area;
use App\Models\Consume;
use App\Models\ImportLog;
use App\Models\Machine;
use App\Models\PartNumber;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportConsumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $filePath,
        public string $importLogId,
        public ?string $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $importLog = ImportLog::find($this->importLogId);
        if ($importLog) {
            $importLog->update(['status' => 'processing']);
        }

        $fullPath = Storage::path($this->filePath);
        $errors = [];
        $processedCount = 0;

        try {
            if (!file_exists($fullPath)) {
                throw new \Exception("File import tidak ditemukan di server: {$this->filePath}");
            }

            $sheets = Excel::toArray(new ConsumeImport, $fullPath);
            $rows = $sheets[0] ?? [];

            if (empty($rows)) {
                throw new \Exception("File Excel tidak memiliki baris data atau kosong.");
            }

            if ($importLog) {
                $importLog->update(['total_rows' => count($rows)]);
            }

            DB::transaction(function () use ($rows, &$errors, &$processedCount) {
                $partCache = [];
                $areaCache = [];
                $machineCache = [];

                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 2; // +1 for 0-index, +1 for header row

                    // Normalize array keys: lowercase and strip spaces/underscores
                    $normalizedRow = [];
                    foreach ($row as $k => $v) {
                        $cleanedKey = strtolower(str_replace([' ', '_', '-'], '', (string) $k));
                        $normalizedRow[$cleanedKey] = $v;
                    }

                    $pnBaanRaw = $normalizedRow['partnumber'] ?? $normalizedRow['pnbaan'] ?? null;
                    $pnBaan = trim((string) $pnBaanRaw);

                    // Skip completely empty rows
                    if ($pnBaan === '' && empty($normalizedRow['date']) && !isset($normalizedRow['qty'])) {
                        continue;
                    }

                    if ($pnBaan === '') {
                        $errors[] = "Baris {$rowNumber}: Kolom Part Number kosong.";
                        continue;
                    }

                    // Look up Part Number
                    if (!isset($partCache[$pnBaan])) {
                        $partCache[$pnBaan] = PartNumber::where('pn_baan', $pnBaan)->first();
                    }
                    $part = $partCache[$pnBaan];

                    if (!$part) {
                        $errors[] = "Baris {$rowNumber}: Part Number '{$pnBaan}' tidak ditemukan di master data.";
                    }

                    // Optional Area Code
                    $rawAreaCode = $normalizedRow['areacode'] ?? $normalizedRow['area'] ?? null;
                    $areaCode = $rawAreaCode !== null && trim((string) $rawAreaCode) !== '' ? trim((string) $rawAreaCode) : null;
                    $areaId = null;

                    if ($areaCode !== null) {
                        if (!array_key_exists($areaCode, $areaCache)) {
                            $areaCache[$areaCode] = Area::where('code', $areaCode)->whereNull('deleted_at')->first();
                        }
                        $area = $areaCache[$areaCode];
                        if (!$area) {
                            $errors[] = "Baris {$rowNumber}: Area code '{$areaCode}' tidak ditemukan.";
                        } else {
                            $areaId = $area->id;
                        }
                    }

                    // Optional Machine Code
                    $rawMachineCode = $normalizedRow['machinecode'] ?? $normalizedRow['machine'] ?? null;
                    $machineCode = $rawMachineCode !== null && trim((string) $rawMachineCode) !== '' ? trim((string) $rawMachineCode) : null;
                    $machineId = null;

                    if ($machineCode !== null) {
                        if (!array_key_exists($machineCode, $machineCache)) {
                            $machineCache[$machineCode] = Machine::where('code', $machineCode)->whereNull('deleted_at')->first();
                        }
                        $machine = $machineCache[$machineCode];
                        if (!$machine) {
                            $errors[] = "Baris {$rowNumber}: Machine code '{$machineCode}' tidak ditemukan.";
                        } else {
                            if ($areaId && $machine->area_id !== $areaId) {
                                $errors[] = "Baris {$rowNumber}: Machine '{$machineCode}' tidak berada di area '{$areaCode}'.";
                            } else {
                                $machineId = $machine->id;
                                if (!$areaId && $machine->area_id) {
                                    $areaId = $machine->area_id;
                                }
                            }
                        }
                    }

                    // Parse Date
                    $rawDate = $normalizedRow['date'] ?? null;
                    $consumedAt = $this->parseIndonesianDate($rawDate);
                    if (!$consumedAt) {
                        $errors[] = "Baris {$rowNumber}: Tanggal '{$rawDate}' tidak valid.";
                    }

                    // Parse Quantity (allowed negative)
                    $rawQty = $normalizedRow['qty'] ?? $normalizedRow['quantity'] ?? 0;
                    $quantity = $this->parseQty($rawQty);

                    // Parse Amount (allowed negative)
                    $rawAmount = $normalizedRow['amount'] ?? null;
                    $amount = $this->parseAmount($rawAmount);

                    // If amount is null, attempt to calculate from price_per_unit
                    if ($amount === null && $part && $part->price_per_unit !== null) {
                        $amount = (float) $part->price_per_unit * abs($quantity);
                    }

                    // If there are errors so far, we do not insert
                    if (!empty($errors)) {
                        continue;
                    }

                    Consume::create([
                        'part_number_id' => $part->id,
                        'area_id' => $areaId,
                        'machine_id' => $machineId,
                        'quantity' => $quantity,
                        'amount' => $amount,
                        'consumed_at' => $consumedAt,
                        'source' => 'import_excel',
                        'created_by' => $this->userId,
                    ]);

                    $processedCount++;
                }

                if (!empty($errors)) {
                    throw new \Exception("Validasi import gagal dengan " . count($errors) . " kesalahan.");
                }
            });

            if ($importLog) {
                $importLog->update([
                    'status' => 'success',
                    'processed_rows' => $processedCount,
                    'error_message' => null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("ImportConsumeJob failed: " . $e->getMessage(), ['errors' => $errors]);

            if ($importLog) {
                $errorMessage = !empty($errors)
                    ? json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : $e->getMessage();

                $importLog->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'processed_rows' => 0,
                ]);
            }
        } finally {
            // Delete temporary file
            try {
                Storage::delete($this->filePath);
            } catch (\Throwable $ex) {
                Log::warning("Could not delete temporary import file {$this->filePath}: " . $ex->getMessage());
            }
        }
    }

    /**
     * Helper to parse Indonesian date string, DateTime or Excel serial number.
     */
    protected function parseIndonesianDate(mixed $val): ?Carbon
    {
        if ($val === null || $val === '') {
            return null;
        }

        if ($val instanceof \DateTimeInterface) {
            return Carbon::instance($val)->startOfDay();
        }

        if (is_numeric($val)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($val))->startOfDay();
            } catch (\Throwable) {
                // Continue to string parsing
            }
        }

        $bulanMap = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6,
            'jul' => 7, 'agu' => 8, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10,
            'nov' => 11, 'des' => 12, 'dec' => 12,
        ];

        $str = strtolower(trim((string) $val));

        // Format: "1 juli 2026", "15-juli-2026", "01/juli/2026"
        if (preg_match('/^(\d{1,2})[\s\-\/]+([a-z]+)[\s\-\/]+(\d{4})$/i', $str, $matches)) {
            $day = (int) $matches[1];
            $monthStr = strtolower($matches[2]);
            $year = (int) $matches[3];

            if (isset($bulanMap[$monthStr])) {
                return Carbon::createFromDate($year, $bulanMap[$monthStr], $day)->startOfDay();
            }
        }

        // Standard format fallback: "YYYY-MM-DD" or "DD-MM-YYYY"
        try {
            return Carbon::parse($val)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Helper to clean and parse integer quantity (handles "- 6 ", commas, spaces).
     */
    protected function parseQty(mixed $val): int
    {
        if (is_int($val)) {
            return $val;
        }

        $str = preg_replace('/\s+/', '', (string) $val);
        $str = str_replace(',', '', $str);

        return is_numeric($str) ? (int) $str : 0;
    }

    /**
     * Helper to clean and parse float amount (handles "- 1,143,600 ", commas, spaces).
     */
    protected function parseAmount(mixed $val): ?float
    {
        if ($val === null || trim((string) $val) === '') {
            return null;
        }

        if (is_float($val) || is_int($val)) {
            return (float) $val;
        }

        $str = preg_replace('/\s+/', '', (string) $val);
        $str = str_replace(',', '', $str);

        return is_numeric($str) ? (float) $str : null;
    }
}

