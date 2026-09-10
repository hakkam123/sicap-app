<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class IndonesianFormatParser
{
    /**
     * Map nama bulan bahasa Indonesia & Inggris ke 2 digit bulan.
     */
    protected static array $monthsMap = [
        'januari' => '01', 'jan' => '01', 'january' => '01',
        'februari' => '02', 'feb' => '02', 'february' => '02',
        'maret' => '03', 'mar' => '03', 'march' => '03',
        'april' => '04', 'apr' => '04',
        'mei' => '05', 'may' => '05',
        'juni' => '06', 'jun' => '06', 'june' => '06',
        'juli' => '07', 'jul' => '07', 'july' => '07',
        'agustus' => '08', 'agt' => '08', 'aug' => '08', 'august' => '08',
        'september' => '09', 'sep' => '09', 'sept' => '09',
        'oktober' => '10', 'okt' => '10', 'oct' => '10', 'october' => '10',
        'november' => '11', 'nov' => '11',
        'desember' => '12', 'des' => '12', 'dec' => '12', 'december' => '12',
    ];

    /**
     * Parse tanggal secara fleksibel dari berbagai format:
     * - "6 juli 2026", "6-Jul-2026", "06/07/2026", "2026-07-06"
     * - DateTime object, Excel serial date number.
     *
     * @param mixed $value
     * @return Carbon|null
     */
    public static function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->startOfDay();
            } catch (\Throwable) {
                // Lanjut ke parser string jika gagal
            }
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // 1. Coba format: "6 juli 2026" / "6-jul-2026" / "06 Juli 2026"
        if (preg_match('/^(\d{1,2})[\s\-\/\.]([a-zA-Z]+)[\s\-\/\.](\d{4})/i', $str, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthKey = strtolower($matches[2]);
            $year = $matches[3];

            if (isset(self::$monthsMap[$monthKey])) {
                $month = self::$monthsMap[$monthKey];
                try {
                    return Carbon::createFromFormat('Y-m-d', "{$year}-{$month}-{$day}")->startOfDay();
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        // 2. Coba format: "dd/mm/yyyy" atau "dd-mm-yyyy"
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $str, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            try {
                return Carbon::createFromFormat('Y-m-d', "{$year}-{$month}-{$day}")->startOfDay();
            } catch (\Throwable) {
                // Lanjut ke fallback
            }
        }

        // 3. Fallback standar Carbon (ISO "2026-07-06", dll)
        try {
            return Carbon::parse($str)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse amount dari format Indonesia / internasional menjadi float.
     * Contoh yang didukung:
     * - "-1.386.000", "- 1.386.000", "1.386.000,50", "-1386000", -1386000, "(1.386.000)"
     *
     * @param mixed $value
     * @return float|null
     */
    public static function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // Deteksi nilai negatif
        $isNegative = str_contains($str, '-') || (str_starts_with($str, '(') && str_ends_with($str, ')'));

        // Bersihkan karakter selain angka, titik, dan koma
        $cleanStr = preg_replace('/[^\d\.,]/', '', $str);
        if ($cleanStr === '') {
            return null;
        }

        $hasDot = str_contains($cleanStr, '.');
        $hasComma = str_contains($cleanStr, ',');

        if ($hasDot && $hasComma) {
            $lastDot = strrpos($cleanStr, '.');
            $lastComma = strrpos($cleanStr, ',');

            if ($lastComma > $lastDot) {
                // Format Indonesia: 1.386.000,50 -> buang titik, ganti koma dengan titik
                $cleanStr = str_replace('.', '', $cleanStr);
                $cleanStr = str_replace(',', '.', $cleanStr);
            } else {
                // Format US: 1,386,000.50 -> buang koma
                $cleanStr = str_replace(',', '', $cleanStr);
            }
        } elseif ($hasDot) {
            // Hanya ada titik. Cek apakah titik ribuan (misal "1.386.000" atau "1.386")
            $dotCount = substr_count($cleanStr, '.');
            if ($dotCount > 1) {
                // Pasti ribuan: 1.386.000 -> 1386000
                $cleanStr = str_replace('.', '', $cleanStr);
            } elseif (preg_match('/^\d{1,3}\.\d{3}$/', $cleanStr)) {
                // Pola ribuan tunggal (misal 1.386 atau 250.000)
                $cleanStr = str_replace('.', '', $cleanStr);
            }
            // Jika desimal seperti "1386.5" atau "1386.50", biarkan titik desimalnya
        } elseif ($hasComma) {
            // Hanya ada koma
            $commaCount = substr_count($cleanStr, ',');
            if ($commaCount > 1) {
                $cleanStr = str_replace(',', '', $cleanStr);
            } else {
                // Bisa desimal Indonesia: 1386,5 -> 1386.5 atau ribuan US: 1,386 -> 1386
                if (preg_match('/^\d{1,3},\d{3}$/', $cleanStr)) {
                    $cleanStr = str_replace(',', '', $cleanStr);
                } else {
                    $cleanStr = str_replace(',', '.', $cleanStr);
                }
            }
        }

        $num = (float) $cleanStr;
        return $isNegative ? -$num : $num;
    }

    /**
     * Parse integer quantity (mendukung "- 4", "-4", 4, dll).
     *
     * @param mixed $value
     * @return int
     */
    public static function parseQty(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        $str = trim((string) $value);
        $isNegative = str_contains($str, '-') || str_contains($str, '(');
        $clean = preg_replace('/[^\d]/', '', $str);

        if ($clean === '') {
            return 0;
        }

        $qty = (int) $clean;
        return $isNegative ? -$qty : $qty;
    }
}

