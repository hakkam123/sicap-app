<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Reading Excel file...');

        // Pastikan file ada di root project
        $filePath = base_path('Master Data Sparepart.xlsx');
        if (!file_exists($filePath)) {
            $filePath = base_path('../Master Data Sparepart.xlsx');
        }

        if (!file_exists($filePath)) {
            $this->command->error('File not found: ' . $filePath);
            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('Sheet2');
        if (!$sheet) {
            $this->command->error("Sheet 'Sheet2' not found in file: " . $filePath);
            return;
        }

        $rows = $sheet->toArray(null, true, true, true); // A,B,C,D keys

        // Skip header (row 1)
        array_shift($rows);

        // ─── STEP 1: Insert Areas (hanya SMT dan FA) ───────────────────
        $this->command->info('Inserting areas...');

        $areaMap = []; // code => id

        $areasData = [
            ['code' => 'SMT', 'name' => 'Surface Mount Technology'],
            ['code' => 'FA',  'name' => 'Final Assembly'],
        ];

        foreach ($areasData as $area) {
            $existing = DB::table('areas')->where('code', $area['code'])->first();

            if ($existing) {
                DB::table('areas')->where('id', $existing->id)->update([
                    'name'       => $area['name'],
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
                $areaMap[$area['code']] = $existing->id;
            } else {
                $id = (string) Str::ulid();
                DB::table('areas')->insert([
                    'id'          => $id,
                    'code'        => $area['code'],
                    'name'        => $area['name'],
                    'description' => null,
                    'deleted_at'  => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $areaMap[$area['code']] = $id;
            }
        }
        $this->command->info('Areas ready: ' . count($areaMap));

        // ─── STEP 2: Kumpulkan semua machine per area dari Excel ────────
        $this->command->info('Collecting machines...');

        // machinesByArea[area_code][machine_name] = true
        $machinesByArea = ['SMT' => [], 'FA' => []];

        foreach ($rows as $row) {
            $area    = trim($row['C'] ?? '');
            $machine = trim($row['D'] ?? '');

            if (!$area || !$machine) continue;
            if ($machine === '#N/A') continue; // skip dirty

            if ($area === 'Common') {
                // Common = masuk ke SMT dan FA
                $machinesByArea['SMT'][$machine] = true;
                $machinesByArea['FA'][$machine]  = true;
            } elseif (isset($machinesByArea[$area])) {
                $machinesByArea[$area][$machine] = true;
            }
        }

        // Insert machines
        $machineMap = []; // "AREA_CODE::machine_name" => id

        foreach ($machinesByArea as $areaCode => $machines) {
            $areaId = $areaMap[$areaCode];
            $i = 1;
            foreach (array_keys($machines) as $machineName) {
                // Generate kode machine: {AREA_CODE}_M{nomor urut}
                $machineCode = $areaCode . '_M' . str_pad($i, 3, '0', STR_PAD_LEFT);

                // Cek apakah sudah ada (dari seed sebelumnya)
                $existing = DB::table('machines')
                    ->where('area_id', $areaId)
                    ->where('name', $machineName)
                    ->first();

                if ($existing) {
                    if ($existing->deleted_at !== null) {
                        DB::table('machines')->where('id', $existing->id)->update([
                            'deleted_at' => null,
                            'updated_at' => now(),
                        ]);
                    }
                    $machineMap[$areaCode . '::' . $machineName] = $existing->id;
                    $i++;
                    continue;
                }

                // Pastikan code unik (tambah suffix jika perlu)
                $finalCode = $machineCode;
                $suffix = 1;
                while (DB::table('machines')->where('code', $finalCode)->exists()) {
                    $finalCode = $machineCode . '_' . $suffix++;
                }

                $id = (string) Str::ulid();
                DB::table('machines')->insert([
                    'id'          => $id,
                    'area_id'     => $areaId,
                    'code'        => $finalCode,
                    'name'        => $machineName,
                    'description' => null,
                    'deleted_at'  => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $machineMap[$areaCode . '::' . $machineName] = $id;
                $i++;
            }
            $this->command->info("Machines inserted for {$areaCode}: " . count($machines));
        }

        // ─── STEP 3: Insert Part Numbers ───────────────────────────────
        $this->command->info('Inserting part numbers...');

        $partMap   = []; // pn_baan => id
        $partCount = 0;

        foreach ($rows as $row) {
            $pnBaan = trim($row['A'] ?? '');
            $desc   = trim($row['B'] ?? '');

            if (!$pnBaan) continue;
            if (isset($partMap[$pnBaan])) continue; // sudah diproses

            // Cek sudah ada di DB
            $existing = DB::table('part_numbers')
                ->where('pn_baan', $pnBaan)
                ->first();

            if ($existing) {
                if ($existing->deleted_at !== null) {
                    DB::table('part_numbers')->where('id', $existing->id)->update([
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                }
                $partMap[$pnBaan] = $existing->id;
                continue;
            }

            $id = (string) Str::ulid();
            DB::table('part_numbers')->insert([
                'id'             => $id,
                'pn_baan'        => $pnBaan,
                'description'    => $desc ?: null,
                'price_per_unit' => null,
                'deleted_at'     => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $partMap[$pnBaan] = $id;
            $partCount++;
        }
        $this->command->info("Part numbers inserted: {$partCount} (Total known: " . count($partMap) . ")");

        // ─── STEP 4: Insert Mapping Part ↔ Area & Machine ──────────────
        $this->command->info('Inserting mappings...');

        // Bersihkan pivot lama
        DB::table('area_part_number')->delete();
        DB::table('machine_part_number')->delete();

        $areaMappingInserts   = [];
        $machineMappingInserts = [];

        // Track yang sudah di-insert (hindari duplikat)
        $insertedAreaPivot    = [];
        $insertedMachinePivot = [];

        foreach ($rows as $row) {
            $pnBaan  = trim($row['A'] ?? '');
            $area    = trim($row['C'] ?? '');
            $machine = trim($row['D'] ?? '');

            if (!$pnBaan || !$area) continue;
            if (!isset($partMap[$pnBaan])) continue;

            $partId = $partMap[$pnBaan];

            // Tentukan area target (Common → SMT + FA)
            $targetAreas = [];
            if ($area === 'Common') {
                $targetAreas = ['SMT', 'FA'];
            } elseif (isset($areaMap[$area])) {
                $targetAreas = [$area];
            } else {
                continue; // area tidak dikenal, skip
            }

            foreach ($targetAreas as $targetArea) {
                $areaId = $areaMap[$targetArea];

                // Pivot area_part_number
                $areaPivotKey = $areaId . '::' . $partId;
                if (!isset($insertedAreaPivot[$areaPivotKey])) {
                    $areaMappingInserts[] = [
                        'area_id'        => $areaId,
                        'part_number_id' => $partId,
                    ];
                    $insertedAreaPivot[$areaPivotKey] = true;
                }

                // Pivot machine_part_number (jika machine valid)
                if ($machine && $machine !== '#N/A') {
                    $machineKey = $targetArea . '::' . $machine;
                    if (isset($machineMap[$machineKey])) {
                        $machineId = $machineMap[$machineKey];
                        $machinePivotKey = $machineId . '::' . $partId;
                        if (!isset($insertedMachinePivot[$machinePivotKey])) {
                            $machineMappingInserts[] = [
                                'machine_id'     => $machineId,
                                'part_number_id' => $partId,
                            ];
                            $insertedMachinePivot[$machinePivotKey] = true;
                        }
                    }
                }
            }

            // Batch insert setiap 500 rows untuk efisiensi dan keamanan limit SQL Server
            if (count($areaMappingInserts) >= 500) {
                DB::table('area_part_number')->insert($areaMappingInserts);
                $areaMappingInserts = [];
            }
            if (count($machineMappingInserts) >= 500) {
                DB::table('machine_part_number')->insert($machineMappingInserts);
                $machineMappingInserts = [];
            }
        }

        // Insert sisa
        if (!empty($areaMappingInserts)) {
            DB::table('area_part_number')->insert($areaMappingInserts);
        }
        if (!empty($machineMappingInserts)) {
            DB::table('machine_part_number')->insert($machineMappingInserts);
        }

        $this->command->info('Area mappings: ' . count($insertedAreaPivot));
        $this->command->info('Machine mappings: ' . count($insertedMachinePivot));
        $this->command->info('✅ MasterDataSeeder selesai!');
    }
}

