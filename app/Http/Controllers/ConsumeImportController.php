<?php

namespace App\Http\Controllers;

use App\Exports\ConsumeTemplateExport;
use App\Imports\ConsumeImport;
use App\Jobs\ImportConsumeJob;
use App\Models\ImportLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConsumeImportController extends Controller
{
    /**
     * Show the import form.
     */
    public function showForm(): Response
    {
        return Inertia::render('Consume/Import');
    }

    /**
     * Handle the uploaded Excel file and dispatch background job.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'file.required' => 'File Excel wajib diunggah.',
            'file.mimes' => 'Format file harus berupa Excel (.xlsx atau .xls).',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        $uploadedFile = $request->file('file');
        $originalFilename = $uploadedFile->getClientOriginalName();
        $storedPath = $uploadedFile->store('imports');

        // Read total rows count
        $fullPath = Storage::path($storedPath);
        $totalRows = 0;
        try {
            $sheets = Excel::toArray(new ConsumeImport, $fullPath);
            $totalRows = count($sheets[0] ?? []);
        } catch (\Throwable $e) {
            $totalRows = 0;
        }

        // Create initial pending log
        $importLog = ImportLog::create([
            'filename' => $originalFilename,
            'status' => 'pending',
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'user_id' => Auth::id(),
        ]);

        // Dispatch background queue job
        ImportConsumeJob::dispatch($storedPath, $importLog->id, Auth::id());

        return redirect()->route('consume.index')->with('success', 'File sedang diproses di background. Cek status pada riwayat import log.');
    }

    /**
     * Download the consume Excel template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new ConsumeTemplateExport, 'template_consume.xlsx');
    }
}

