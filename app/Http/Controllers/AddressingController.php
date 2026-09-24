<?php

namespace App\Http\Controllers;

use App\Exports\AddressingTemplateExport;
use App\Imports\AddressingImport;
use App\Models\PartNumber;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AddressingController extends Controller
{
    /**
     * Store or assign addressing & part number code to a part number from modal.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'part_number_id'   => ['required', 'string', 'exists:part_numbers,id'],
            'part_number_code' => ['nullable', 'string', 'max:100'],
            'addressing'       => ['nullable', 'string', 'max:255'],
        ], [
            'part_number_id.required' => 'Part Number wajib dipilih.',
            'part_number_id.exists'   => 'Part Number tidak valid atau belum terdaftar.',
        ]);

        $part = PartNumber::findOrFail($validated['part_number_id']);

        $part->update([
            'part_number_code' => isset($validated['part_number_code']) && trim((string) $validated['part_number_code']) !== ''
                ? trim((string) $validated['part_number_code'])
                : null,
            'addressing'       => isset($validated['addressing']) && trim((string) $validated['addressing']) !== ''
                ? trim((string) $validated['addressing'])
                : null,
        ]);

        PartNumber::clearCatalogCache();

        return redirect()->back()->with('success', "Addressing untuk Part Number '{$part->pn_baan}' berhasil disimpan.");
    }

    /**
     * Update only part_number_code and addressing for a specific part number.
     */
    public function update(Request $request, PartNumber $partNumber): RedirectResponse
    {
        $validated = $request->validate([
            'part_number_code' => ['nullable', 'string', 'max:100'],
            'addressing'       => ['nullable', 'string', 'max:255'],
        ]);

        $partNumber->update([
            'part_number_code' => isset($validated['part_number_code']) && trim((string) $validated['part_number_code']) !== ''
                ? trim((string) $validated['part_number_code'])
                : null,
            'addressing'       => isset($validated['addressing']) && trim((string) $validated['addressing']) !== ''
                ? trim((string) $validated['addressing'])
                : null,
        ]);

        PartNumber::clearCatalogCache();

        return redirect()->back()->with('success', "Addressing untuk Part Number '{$partNumber->pn_baan}' berhasil diperbarui.");
    }

    /**
     * Import addressing and part number code mass update from Excel.
     */
    public function import(Request $request, ImportService $importService)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.mimes'    => 'Format file harus .xlsx atau .xls.',
            'file.max'      => 'Ukuran file maksimal 10MB.',
        ]);

        $result = $importService->process(
            $request->file('file'),
            'addressing',
            new AddressingImport()
        );

        PartNumber::clearCatalogCache();

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
     * Download Excel template for addressing import.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new AddressingTemplateExport(), 'template_addressing.xlsx');
    }
}
