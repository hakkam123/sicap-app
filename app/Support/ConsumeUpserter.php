<?php

namespace App\Support;

use App\Models\Consume;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Simpan transaksi consume tanpa duplikasi lintas sumber (import_excel, api, manual).
 *
 * Transaksi dianggap sama bila PN + tanggal + qty identik (amount TIDAK ikut dicocokkan,
 * karena bisa berbeda pembulatan antar sumber). Jika sudah ada, baris lama ditimpa dengan
 * data terbaru (amount, source, updated_at), bukan ditambah baris baru. Area/machine lama
 * tidak ditimpa agar koreksi manual tetap terjaga.
 *
 * Pencocokan bersifat multiset: dua transaksi identik di hari yang sama (mis. dua dokumen
 * GI berbeda) tetap tersimpan dua baris. Bila ada beberapa kandidat, yang amount-nya sama
 * persis dipilih lebih dulu.
 */
class ConsumeUpserter
{
    /**
     * @param array<int, array{part_number_id:string, consumed_at:\DateTimeInterface, quantity:int, amount:float|null, source:string, area_id?:?string, machine_id?:?string, created_by?:?string}> $records
     * @return array{inserted:int, updated:int}
     */
    public static function upsertLatest(array $records): array
    {
        if (empty($records)) {
            return ['inserted' => 0, 'updated' => 0];
        }

        $dates = array_map(fn($r) => Carbon::instance($r['consumed_at']), $records);
        $minDate = min($dates)->copy()->startOfDay();
        $maxDate = max($dates)->copy()->endOfDay();
        $partIds = array_values(array_unique(array_column($records, 'part_number_id')));

        // Pool baris yang sudah ada: key (PN|tanggal|qty) => [id => amount, ...]
        $pool = [];
        foreach (array_chunk($partIds, 1000) as $chunk) {
            DB::table('consumes')
                ->whereBetween('consumed_at', [$minDate, $maxDate])
                ->whereIn('part_number_id', $chunk)
                ->orderBy('created_at')
                ->select('id', 'part_number_id', 'consumed_at', 'quantity', 'amount')
                ->get()
                ->each(function ($r) use (&$pool) {
                    $pool[self::key($r->part_number_id, $r->consumed_at, $r->quantity)][$r->id] = self::absMoney($r->amount);
                });
        }

        $now = now();
        $toInsert = [];
        $toUpdate = []; // "source|quantity|amount" => [id, ...]

        foreach ($records as $rec) {
            $key = self::key($rec['part_number_id'], $rec['consumed_at'], $rec['quantity']);

            if (!empty($pool[$key])) {
                // Utamakan kandidat dengan amount sama (absolut), kalau tidak ada ambil yang terlama
                $id = array_search(self::absMoney($rec['amount']), $pool[$key], true);
                if ($id === false) {
                    $id = array_key_first($pool[$key]);
                }
                unset($pool[$key][$id]);

                // Timpa dengan nilai terbaru apa adanya (termasuk tandanya)
                $amount = $rec['amount'] === null ? '' : sprintf('%.2f', (float) $rec['amount']);
                $toUpdate[$rec['source'] . '|' . (int) $rec['quantity'] . '|' . $amount][] = $id;
                continue;
            }

            $toInsert[] = [
                'id' => (string) Str::ulid(),
                'part_number_id' => $rec['part_number_id'],
                'area_id' => $rec['area_id'] ?? null,
                'machine_id' => $rec['machine_id'] ?? null,
                'quantity' => $rec['quantity'],
                'amount' => $rec['amount'],
                'consumed_at' => $rec['consumed_at'],
                'source' => $rec['source'],
                'created_by' => $rec['created_by'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $updated = 0;
        DB::transaction(function () use ($toInsert, $toUpdate, $now, &$updated) {
            // 11 kolom/baris → 100 baris = 1.100 parameter (batas SQL Server 2.100)
            foreach (array_chunk($toInsert, 100) as $chunk) {
                Consume::insert($chunk);
            }

            // Dikelompokkan per (source, qty, amount) agar satu UPDATE untuk banyak baris
            foreach ($toUpdate as $group => $ids) {
                [$source, $quantity, $amount] = explode('|', $group, 3);
                foreach (array_chunk($ids, 1000) as $chunk) {
                    $updated += DB::table('consumes')
                        ->whereIn('id', $chunk)
                        ->update([
                            'quantity' => (int) $quantity,
                            'amount' => $amount === '' ? null : $amount,
                            'source' => $source,
                            'updated_at' => $now,
                        ]);
                }
            }
        });

        return ['inserted' => count($toInsert), 'updated' => $updated];
    }

    /**
     * Qty dibandingkan secara absolut: API BAAN mengirim negatif (-3), sedangkan
     * Excel bisa berisi positif (3) untuk transaksi consume yang sama.
     */
    private static function key(string $partId, mixed $date, mixed $qty): string
    {
        return $partId . '|' . Carbon::parse($date)->format('Y-m-d') . '|' . abs((int) $qty);
    }

    /** Hanya untuk memilih kandidat terbaik (absolut, tanda diabaikan). */
    private static function absMoney(mixed $amount): string
    {
        return $amount === null ? '' : sprintf('%.2f', abs((float) $amount));
    }
}
