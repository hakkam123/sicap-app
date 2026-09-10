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
    public function import(Request $request, \App\Services\ImportService $importService)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.mimes' => 'Format file harus .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ]);

        $result = $importService->process(
            $request->file('file'),
            'machine',
            new MachineImport()
        );

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message'])
                ->with('import_errors', $result['errors']);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Download machine Excel template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new MachineTemplateExport(), 'template_machine.xlsx');
    }
}
