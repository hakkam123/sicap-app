<?php

namespace App\Imports;

use App\Models\Consume;
use App\Models\PartNumber;
use App\Support\IndonesianFormatParser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

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

                // Normalisasi kunci header (lowercase, buang spasi dan underscore)
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

                // Skip jika baris benar-benar kosong
                if ($pnBaan === '' && empty($rawDate) && ($rawQty === null || $rawQty === '') && ($rawAmount === null || $rawAmount === '')) {
                    continue;
                }

                // 1. Validasi Part Number
                if ($pnBaan === '') {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'Part Number' wajib diisi.";
                    continue;
                }

                $partNumber = PartNumber::where('pn_baan', $pnBaan)->first();
                if (!$partNumber) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Part Number '{$pnBaan}' tidak ditemukan di master data.";
                    continue;
                }

                // 2. Validasi & Parsing Tanggal (Format fleksibel termasuk bahasa Indonesia)
                $consumedAt = IndonesianFormatParser::parseDate($rawDate);
                if (!$consumedAt) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Format tanggal '{$rawDate}' tidak valid.";
                    continue;
                }

                // 3. Validasi & Parsing Quantity (Integer, boleh bernilai negatif untuk pengeluaran)
                if ($rawQty === null || trim((string) $rawQty) === '') {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'qty' wajib diisi.";
                    continue;
                }
                $qty = IndonesianFormatParser::parseQty($rawQty);
                if ($qty === 0) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'qty' tidak boleh bernilai 0.";
                    continue;
                }

                // 4. Validasi & Parsing Amount (Format Indonesia: titik ribuan, koma desimal, boleh negatif)
                if ($rawAmount === null || trim((string) $rawAmount) === '') {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Kolom 'Amount' wajib diisi.";
                    continue;
                }
                $amount = IndonesianFormatParser::parseAmount($rawAmount);
                if ($amount === null) {
                    $this->errorCount++;
                    $this->errors[] = "Baris {$rowNumber}: Format nominal Amount '{$rawAmount}' tidak valid.";
                    continue;
                }

                // Simpan transaksi consume (area_id & machine_id bernilai null sesuai format baru)
                Consume::create([
                    'id' => (string) Str::ulid(),
                    'part_number_id' => $partNumber->id,
                    'area_id' => null,
                    'machine_id' => null,
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
