<?php

namespace App\Jobs;

use App\Imports\ConsumeImport;
use App\Models\Consume;
use App\Models\ImportLog;
use App\Models\PartNumber;
use App\Support\IndonesianFormatParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

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

                foreach ($rows as $index => $row) {
                    $rowNumber = $index + 2; // +1 for 0-index, +1 for header row

                    // Normalize array keys: lowercase and strip spaces/underscores
                    $normalizedRow = [];
                    foreach ($row as $k => $v) {
                        $cleanedKey = strtolower(str_replace([' ', '_', '-'], '', (string) $k));
                        $normalizedRow[$cleanedKey] = $v;
                    }

                    $rawDate = $normalizedRow['date'] ?? $normalizedRow['consumedat'] ?? $row['Date'] ?? $row['date'] ?? null;
                    $rawPn = $normalizedRow['partnumber'] ?? $normalizedRow['pnbaan'] ?? $row['Part Number'] ?? $row['part_number'] ?? $row['pn_baan'] ?? null;
                    $rawQty = $normalizedRow['qty'] ?? $normalizedRow['quantity'] ?? $row['qty'] ?? $row['quantity'] ?? null;
                    $rawAmount = $normalizedRow['amount'] ?? $row['Amount'] ?? $row['amount'] ?? null;

                    $pnBaan = trim((string) $rawPn);

                    // Skip completely empty rows
                    if ($pnBaan === '' && empty($rawDate) && ($rawQty === null || $rawQty === '') && ($rawAmount === null || $rawAmount === '')) {
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

                    // Parse Date
                    $consumedAt = IndonesianFormatParser::parseDate($rawDate);
                    if (!$consumedAt) {
                        $errors[] = "Baris {$rowNumber}: Format tanggal '{$rawDate}' tidak valid.";
                    }

                    // Parse Quantity (allowed negative)
                    if ($rawQty === null || trim((string) $rawQty) === '') {
                        $errors[] = "Baris {$rowNumber}: Kolom 'qty' kosong.";
                    }
                    $quantity = IndonesianFormatParser::parseQty($rawQty);
                    if ($quantity === 0) {
                        $errors[] = "Baris {$rowNumber}: Kolom 'qty' tidak boleh bernilai 0.";
                    }

                    // Parse Amount (allowed negative, format Indonesia)
                    if ($rawAmount === null || trim((string) $rawAmount) === '') {
                        $errors[] = "Baris {$rowNumber}: Kolom 'Amount' kosong.";
                    }
                    $amount = IndonesianFormatParser::parseAmount($rawAmount);
                    if ($amount === null) {
                        $errors[] = "Baris {$rowNumber}: Format nominal Amount '{$rawAmount}' tidak valid.";
                    }

                    // If there are errors so far, we do not insert
                    if (!empty($errors)) {
                        continue;
                    }

                    Consume::create([
                        'part_number_id' => $part->id,
                        'area_id' => null,
                        'machine_id' => null,
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
            try {
                Storage::delete($this->filePath);
            } catch (\Throwable $ex) {
                Log::warning("Could not delete temporary import file {$this->filePath}: " . $ex->getMessage());
            }
        }
    }
}
