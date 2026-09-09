<?php

namespace App\Http\Controllers;

use App\Exports\MachineTemplateExport;
use App\Http\Requests\MachineRequest;
use App\Imports\MachineImport;
use App\Models\Area;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MachineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Machine::query()->with('area')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($areaId = $request->input('area_id')) {
            $query->where('area_id', $areaId);
        }

        $machines = $query->paginate((int) $request->input('per_page', 10))->withQueryString();
        $areas = Area::orderBy('name')->get(['id', 'code', 'name']);

        return Inertia::render('Machine/Index', [
            'machines' => $machines,
            'areas' => $areas,
            'filters' => [
                'search' => $request->input('search', ''),
                'area_id' => $request->input('area_id', ''),
                'per_page' => (int) $request->input('per_page', 10),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MachineRequest $request): RedirectResponse
    {
        Machine::create($request->validated());

        return redirect()->route('machines.index')->with('success', 'Machine berhasil ditambahkan');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MachineRequest $request, Machine $machine): RedirectResponse
    {
        $machine->update($request->validated());

        return redirect()->route('machines.index')->with('success', 'Machine berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Machine $machine): RedirectResponse
    {
        $hasConsumes = $machine->consumes()->exists();

        if ($hasConsumes) {
            return redirect()->back()->with('error', 'Machine tidak dapat dihapus karena masih memiliki data terkait');
        }

        $machine->delete();

        return redirect()->route('machines.index')->with('success', 'Machine berhasil dihapus');
    }

    /**
     * Import machines from Excel.
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

        $importer = new MachineImport();

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

        $msg = "Import Machine selesai: {$importer->successCount} data berhasil diproses.";
        if ($importer->updatedCount > 0) {
            $msg .= " ({$importer->updatedCount} data diperbarui)";
        }
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
     * Download machine Excel template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new MachineTemplateExport(), 'template_machine.xlsx');
    }
}
