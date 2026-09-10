<?php

namespace App\Http\Controllers;

use App\Exports\AreaTemplateExport;
use App\Http\Requests\AreaRequest;
use App\Imports\AreaImport;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Area::query()->withCount('machines')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $areas = $query->paginate((int) $request->input('per_page', 10))->withQueryString();

        return Inertia::render('Area/Index', [
            'areas' => $areas,
            'filters' => [
                'search' => $request->input('search', ''),
                'per_page' => (int) $request->input('per_page', 10),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AreaRequest $request): RedirectResponse
    {
        Area::create($request->validated());

        return redirect()->route('areas.index')->with('success', 'Area berhasil ditambahkan');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return redirect()->route('areas.index')->with('success', 'Area berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Area $area): RedirectResponse
    {
        $hasMachines = $area->machines()->exists();
        $hasConsumes = $area->consumes()->exists();

        if ($hasMachines || $hasConsumes) {
            return redirect()->back()->with('error', 'Area tidak dapat dihapus karena masih memiliki data terkait');
        }

        $area->delete();

        return redirect()->route('areas.index')->with('success', 'Area berhasil dihapus');
    }

    /**
     * Import areas from Excel.
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
            'area',
            new AreaImport()
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
     * Download area Excel template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new AreaTemplateExport(), 'template_area.xlsx');
    }
}
