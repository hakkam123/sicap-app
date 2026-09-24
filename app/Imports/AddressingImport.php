<?php

namespace App\Imports;

use App\Models\PartNumber;
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
     * Process import rows with partial success support and bulk pre-fetching.
     *
     * @param array $array
     */
    public function array(array $array): void
    {
        // 1. Kumpulkan semua pn_baan unik yang valid dari file Excel
        $pnList = [];
        foreach ($array as $row) {
            $pn = isset($row['pn_baan']) ? trim((string) $row['pn_baan']) : '';
            if ($pn !== '') {
                $pnList[$pn] = true;
            }
        }
        $uniquePns = array_keys($pnList);

        // 2. Bulk lookup PartNumber (chunk 500 untuk keamanan batas parameter SQL Server)
        $partMap = collect();
        if (!empty($uniquePns)) {
            collect($uniquePns)->chunk(500)->each(function ($chunk) use (&$partMap) {
                $parts = PartNumber::whereIn('pn_baan', $chunk)->get()->keyBy('pn_baan');
                $partMap = $partMap->merge($parts);
            });
        }

        // 3. Iterasi setiap baris Excel dan lakukan update (Partial Success tanpa rollback global)
        foreach ($array as $index => $row) {
            $rowNumber = $index + 2; // Baris 1 adalah heading

            $pnBaan = isset($row['pn_baan']) ? trim((string) $row['pn_baan']) : '';
            $partNumberCode = isset($row['part_number_code']) && trim((string) $row['part_number_code']) !== ''
                ? trim((string) $row['part_number_code'])
                : null;
            $addressing = isset($row['addressing']) && trim((string) $row['addressing']) !== ''
                ? trim((string) $row['addressing'])
                : null;

            // Lewati jika seluruh kolom pada baris ini benar-benar kosong
            if ($pnBaan === '' && $partNumberCode === null && $addressing === null) {
                continue;
            }

            // Hitung baris yang benar-benar diproses
            $this->totalCount++;

            // Validasi keberadaan pn_baan
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

            // Cek apakah Part Number terdaftar di database
            /** @var PartNumber|null $partNumber */
            $partNumber = $partMap->get($pnBaan);
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

            // Simpan perubahan data per baris
            try {
                $partNumber->part_number_code = $partNumberCode;
                $partNumber->addressing = $addressing;
                $partNumber->save();

                $this->successCount++;
            } catch (\Throwable $e) {
                $this->errorCount++;
                $this->errors[] = [
                    'row'     => $rowNumber,
                    'field'   => 'database',
                    'value'   => $pnBaan,
                    'message' => "Baris {$rowNumber}: Gagal menyimpan data ({$e->getMessage()}).",
                ];
            }
        }

        // Sinkronkan counter updated dengan counter sukses
        $this->updatedCount = $this->successCount;
    }

    public function headingRow(): int
    {
        return 1;
    }
}
