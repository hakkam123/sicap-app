<?php

namespace App\Http\Controllers;

use App\Exports\MappingTemplateExport;
use App\Imports\MappingImport;
use App\Models\Area;
use App\Models\PartNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MappingController extends Controller
{
    /**
     * Display the unified mapping interface with tab switcher:
     * - Tab 1: Data Mapping (Pivot records)
     * - Tab 2: Daftar Part Number (Direct assignment)
     */
    public function index(Request $request): Response
    {
        $partQuery = PartNumber::query()
            ->withCount(['areas', 'machines'])
            ->whereNull('deleted_at')
            ->orderBy('pn_baan');

        if ($searchPart = $request->input('search_part')) {
            $partQuery->where(function ($q) use ($searchPart) {
                $q->where('pn_baan', 'like', "%{$searchPart}%")
                  ->orWhere('description', 'like', "%{$searchPart}%");
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        $partNumbers = $partQuery->paginate($perPage)->withQueryString();

        $areas = Area::with(['machines' => fn($q) => $q->whereNull('deleted_at')->orderBy('name')])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Mapping/Index', [
            'partNumbers' => $partNumbers,
            'mappings'    => $this->mappingList($request),
            'areas'       => $areas,
            'filters'     => array_merge($request->only(['search', 'search_part', 'area_id', 'tab']), ['per_page' => $perPage]),
        ]);
    }

    /**
     * Get paginated mappings list from pivot tables.
     */
    public function mappingList(Request $request)
    {
        $mappings = DB::table('area_part_number')
            ->join('part_numbers', 'area_part_number.part_number_id', '=', 'part_numbers.id')
            ->join('areas', 'area_part_number.area_id', '=', 'areas.id')
            ->leftJoin('machine_part_number', 'machine_part_number.part_number_id', '=', 'part_numbers.id')
            ->leftJoin('machines', function ($join) {
                $join->on('machines.id', '=', 'machine_part_number.machine_id')
                     ->whereColumn('machines.area_id', 'areas.id');
            })
            ->whereNull('part_numbers.deleted_at')
            ->whereNull('areas.deleted_at')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('part_numbers.pn_baan', 'like', "%{$search}%")
                       ->orWhere('part_numbers.description', 'like', "%{$search}%");
                });
            })
            ->when($request->area_id, fn($q, $areaId) => $q->where('areas.id', $areaId))
            ->select([
                'part_numbers.id as part_id',
                'part_numbers.pn_baan',
                'part_numbers.description as part_desc',
                'areas.id as area_id',
                'areas.name as area_name',
                'areas.code as area_code',
                'machines.id as machine_id',
                'machines.name as machine_name',
                'machines.code as machine_code',
            ])
            ->orderBy('part_numbers.pn_baan')
            ->paginate((int) $request->input('per_page', 10))
            ->withQueryString();

        return $mappings;
    }

    /**
     * Get the mapped area_ids and machine_ids for a specific part number.
     */
    public function getPartDetail(PartNumber $partNumber): JsonResponse
    {
        $partNumber->load(['areas:id', 'machines:id']);

        return response()->json([
            'area_ids' => $partNumber->areas->pluck('id'),
            'machine_ids' => $partNumber->machines->pluck('id'),
        ]);
    }

    /**
     * Sync both Area and Machine relationships for a Part Number in one request.
     */
    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'part_number_id' => ['required', 'string', 'exists:part_numbers,id'],
            'area_ids' => ['required', 'array'],
            'area_ids.*' => ['string', 'exists:areas,id'],
            'machine_ids' => ['nullable', 'array'],
            'machine_ids.*' => ['string', 'exists:machines,id'],
        ], [
            'part_number_id.required' => 'Part number wajib dipilih.',
            'part_number_id.exists' => 'Part number tidak valid.',
            'area_ids.required' => 'Minimal pilih satu area untuk mapping.',
            'area_ids.array' => 'Format pilihan area tidak valid.',
            'area_ids.*.exists' => 'Area yang dipilih tidak valid.',
            'machine_ids.*.exists' => 'Machine yang dipilih tidak valid.',
        ]);

        DB::transaction(function () use ($validated) {
            $partNumber = PartNumber::findOrFail($validated['part_number_id']);
            $partNumber->areas()->sync($validated['area_ids']);
            $partNumber->machines()->sync($validated['machine_ids'] ?? []);
        });

        return redirect()->back()->with('success', 'Mapping berhasil disimpan');
    }

    /**
     * Import mapping from Excel file (pn_baan, area_code, machine_code).
     */
    public function importMapping(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ]);

        $importer = new MappingImport();
        try {
            Excel::import($importer, $request->file('file'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal memproses file Excel: ' . $e->getMessage());
        }

        $message = "Import mapping selesai: {$importer->successCount} data berhasil diproses.";
        if ($importer->errorCount > 0) {
            $message .= " ({$importer->errorCount} baris tidak ditemukan/dilewati)";
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Download mapping Excel template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new MappingTemplateExport(), 'template_mapping.xlsx');
    }
}
