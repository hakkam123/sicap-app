<?php

namespace App\Http\Controllers;

use App\Exports\ConsumeExport;
use App\Exports\ConsumeTemplateExport;
use App\Http\Requests\ConsumeRequest;
use App\Imports\ConsumeImport;
use App\Models\Area;
use App\Models\Consume;
use App\Models\ImportLog;
use App\Models\Machine;
use App\Models\PartNumber;
use App\Models\SyncSchedule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConsumeController extends Controller
{
    /**
     * Display the unified consume listing, filters, modals, and recent import logs.
     */
    public function index(Request $request): Response
    {
        $query = Consume::with([
            'partNumber:id,pn_baan,description',
            'area:id,code,name',
            'machine:id,code,name',
            'creator:id,name',
        ]);

        // 1. Search Filter (by Part Number PN or Description)
        if ($search = $request->input('search')) {
            $query->whereHas('partNumber', function ($partQuery) use ($search) {
                $partQuery->where('pn_baan', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 2. Area Filter
        if ($areaId = $request->input('area_id')) {
            $query->where('area_id', $areaId);
        }

        // 3. Machine Filter
        if ($machineId = $request->input('machine_id')) {
            $query->where('machine_id', $machineId);
        }

        // 4. Date Range Filters
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('consumed_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('consumed_at', '<=', $dateTo);
        }

        $consumes = $query->orderBy('consumed_at', 'DESC')->paginate((int) $request->input('per_page', 10))->withQueryString();

        $areas = Area::select('id', 'code', 'name')->whereNull('deleted_at')->orderBy('name')->get();
        $importLogs = ImportLog::with('user:id,name')->orderBy('created_at', 'DESC')->limit(10)->get();
        $partNumbers = PartNumber::select('id', 'pn_baan', 'description')
            ->whereNull('deleted_at')
            ->orderBy('pn_baan')
            ->get();

        $rawLastSync = Cache::get('last_api_sync_at')
            ?? Consume::where('source', 'api')->latest('created_at')->value('created_at')
            ?? Consume::latest('created_at')->value('created_at');

        $lastSyncFormatted = $rawLastSync
            ? Carbon::parse($rawLastSync)->setTimezone('Asia/Jakarta')->format('H:i:s \W\I\B, d/m/Y')
            : null;

        $syncSchedules = SyncSchedule::orderBy('time')->get();

        return Inertia::render('Consume/Index', [
            'consumes' => $consumes,
            'areas' => $areas,
            'filters' => array_merge($request->only(['search', 'area_id', 'machine_id', 'date_from', 'date_to']), ['per_page' => (int) $request->input('per_page', 10)]),
            'importLogs' => $importLogs,
            'partNumbers' => $partNumbers,
            'lastSyncAt' => $lastSyncFormatted,
            'syncSchedules' => $syncSchedules,
        ]);
    }

    /**
     * Get machines filtered by area ID via AJAX.
     */
    public function getMachinesByArea(Request $request): JsonResponse
    {
        $machines = Machine::where('area_id', $request->area_id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json($machines);
    }

    /**
     * Store a newly created consume record in storage (positive/negative quantity).
     */
    public function store(ConsumeRequest $request): RedirectResponse
    {
        Consume::create([
            'part_number_id' => $request->part_number_id,
            'area_id'        => $request->area_id,
            'machine_id'     => $request->machine_id,
            'quantity'       => (int) $request->quantity,
            'amount'         => (float) $request->amount,
            'consumed_at'    => $request->consumed_at,
            'source'         => 'manual',
            'created_by'     => Auth::id(),
        ]);

        return redirect()->route('consume.index')->with('success', 'Data consume berhasil ditambahkan');
    }

    /**
     * Update the specified consume record in storage.
     */
    public function update(ConsumeRequest $request, Consume $consume): RedirectResponse
    {
        $consume->update([
            'part_number_id' => $request->part_number_id,
            'area_id'        => $request->area_id,
            'machine_id'     => $request->machine_id,
            'quantity'       => (int) $request->quantity,
            'amount'         => (float) $request->amount,
            'consumed_at'    => $request->consumed_at,
        ]);

        return redirect()->route('consume.index')->with('success', 'Data consume berhasil diperbarui');
    }

    /**
     * Remove the specified consume record from storage.
     */
    public function destroy(Consume $consume): RedirectResponse
    {
        $consume->delete();

        return redirect()->route('consume.index')->with('success', 'Data consume berhasil dihapus');
    }

    /**
     * Export filtered consume records to Excel.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['search', 'area_id', 'machine_id', 'date_from', 'date_to']);
        $filename = 'consume_report_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ConsumeExport($filters), $filename);
    }

    /**
     * Import consume records from Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.mimes' => 'Format file harus .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ]);

        $importer = new ConsumeImport();

        try {
            Excel::import($importer, $request->file('file'));
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Gagal memproses file Excel: ' . $e->getMessage(),
                    'errors' => [$e->getMessage()],
                ], 422);
            }
            return redirect()->back()->with('error', 'Gagal memproses file Excel: ' . $e->getMessage());
        }

        if ($importer->errorCount > 0 && $importer->successCount === 0) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Import gagal. Terdapat ' . $importer->errorCount . ' baris bermasalah.',
                    'errors' => $importer->errors,
                ], 422);
            }
            return redirect()->back()->with('error', "Import gagal: {$importer->errorCount} baris tidak valid.")->with('import_errors', $importer->errors);
        }

        $msg = "Import Consume selesai: {$importer->successCount} data berhasil diproses.";
        if ($importer->errorCount > 0) {
            $msg .= " - Terdapat {$importer->errorCount} baris dilewati.";
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'errors' => $importer->errors,
                'success_count' => $importer->successCount,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Download consume Excel template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new ConsumeTemplateExport(), 'template_consume.xlsx');
    }

    /**
     * Trigger manual sync from external API via UI.
     */
    public function syncApi(\App\Services\ConsumeSyncService $syncService): RedirectResponse
    {
        try {
            $result = $syncService->sync(null, Auth::id());

            if (($result['status'] ?? '') === 'warning') {
                return redirect()->route('consume.index')->with('warning', $result['message']);
            }

            $count = $result['synced_count'] ?? 0;
            return redirect()->route('consume.index')->with('success', "Sinkronisasi API berhasil: {$count} data berhasil disinkronkan.");
        } catch (\Throwable $e) {
            return redirect()->route('consume.index')->with('error', "Sinkronisasi API gagal: {$e->getMessage()}");
        }
    }

    /**
     * Update the dynamic sync schedules.
     */
    public function updateSyncSchedules(Request $request): RedirectResponse
    {
        $request->validate([
            'schedules' => ['present', 'array'],
            'schedules.*.time' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):([0-5]\d)$/'],
            'schedules.*.is_active' => ['nullable', 'boolean'],
        ], [
            'schedules.array' => 'Data jadwal tidak valid.',
            'schedules.*.time.required' => 'Format jam wajib diisi.',
            'schedules.*.time.regex' => 'Format jam harus HH:MM (contoh: 08:30).',
        ]);

        $schedules = $request->input('schedules', []);

        DB::transaction(function () use ($schedules) {
            SyncSchedule::query()->delete();

            foreach ($schedules as $item) {
                if (!empty($item['time'])) {
                    SyncSchedule::create([
                        'time' => $item['time'],
                        'is_active' => isset($item['is_active']) ? (bool) $item['is_active'] : true,
                    ]);
                }
            }
        });

        return redirect()->route('consume.index')->with('success', 'Pengaturan jadwal sinkronisasi otomatis berhasil disimpan.');
    }
}
