<?php

namespace App\Http\Controllers;

use App\Exports\PartNumberTemplateExport;
use App\Http\Requests\PartNumberRequest;
use App\Imports\PartNumberImport;
use App\Models\Area;
use App\Models\PartNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PartNumberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = PartNumber::query()
            ->with(['areas:id,code,name', 'machines:id,code,name,area_id'])
            ->withCount(['areas', 'machines'])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('pn_baan', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $partNumbers = $query->paginate((int) $request->input('per_page', 10))->withQueryString();

        // Ambil data area beserta mesin untuk keperluan modal mapping
        $areas = Area::with(['machines' => fn($q) => $q->whereNull('deleted_at')->orderBy('name')])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('PartNumber/Index', [
            'partNumbers' => $partNumbers,
            'areas'       => $areas,
            'filters'     => [
                'search'   => $request->input('search', ''),
                'per_page' => (int) $request->input('per_page', 10),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PartNumberRequest $request): RedirectResponse
    {
        $partNumber = PartNumber::create($request->validated());

        if ($request->has('area_ids')) {
            $partNumber->areas()->sync($request->input('area_ids', []));
        }

        if ($request->has('machine_ids')) {
            $partNumber->machines()->sync($request->input('machine_ids', []));
        }

        return redirect()->route('part-numbers.index')->with('success', 'Part Number berhasil ditambahkan');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PartNumberRequest $request, PartNumber $partNumber): RedirectResponse
    {
        $partNumber->update($request->validated());

        if ($request->has('area_ids')) {
            $partNumber->areas()->sync($request->input('area_ids', []));
        }

        if ($request->has('machine_ids')) {
            $partNumber->machines()->sync($request->input('machine_ids', []));
        }

        return redirect()->route('part-numbers.index')->with('success', 'Part Number berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PartNumber $partNumber): RedirectResponse
    {
        $hasConsumes = $partNumber->consumes()->exists();

        if ($hasConsumes) {
            return redirect()->back()->with('error', 'Part Number tidak dapat dihapus karena masih memiliki data terkait');
        }

        $partNumber->delete();

        return redirect()->route('part-numbers.index')->with('success', 'Part Number berhasil dihapus');
    }

    /**
     * Import part numbers from Excel.
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
            'part_number',
            new PartNumberImport()
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
     * Download part number Excel template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new PartNumberTemplateExport(), 'template_part_number.xlsx');
    }
}
