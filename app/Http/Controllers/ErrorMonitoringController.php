<?php

namespace App\Http\Controllers;

use App\Exports\ImportErrorExport;
use App\Models\ImportLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ErrorMonitoringController extends Controller
{
    /**
     * Display a listing of error logs and import audit history.
     */
    public function index(Request $request): Response
    {
        $query = ImportLog::with('user:id,name,email')->latest('created_at');

        if ($feature = $request->input('feature')) {
            $query->where('feature', $feature);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('filename', 'like', "%{$search}%")
                  ->orWhere('error_message', 'like', "%{$search}%");
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $perPage = (int) $request->input('per_page', 10);
        $logs = $query->paginate($perPage)->withQueryString();

        // Features list for filter dropdown
        $features = [
            ['value' => '', 'label' => 'Semua Fitur'],
            ['value' => 'consume', 'label' => 'Consume'],
            ['value' => 'part_number', 'label' => 'Part Number'],
            ['value' => 'area', 'label' => 'Area'],
            ['value' => 'machine', 'label' => 'Machine'],
            ['value' => 'mapping', 'label' => 'Mapping Part'],
            ['value' => 'user', 'label' => 'User'],
        ];

        return Inertia::render('ErrorMonitoring/Index', [
            'logs' => $logs,
            'features' => $features,
            'filters' => [
                'feature' => $request->input('feature', ''),
                'status' => $request->input('status', ''),
                'search' => $request->input('search', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Return single import log detail as JSON.
     */
    public function show(ImportLog $importLog): JsonResponse
    {
        $importLog->load('user:id,name,email');

        return response()->json([
            'id' => $importLog->id,
            'feature' => $importLog->feature,
            'feature_label' => $importLog->feature_label,
            'filename' => $importLog->filename,
            'status' => $importLog->status,
            'total_rows' => $importLog->total_rows,
            'processed_rows' => $importLog->processed_rows,
            'success_rows' => $importLog->success_rows,
            'failed_rows' => $importLog->failed_rows,
            'progress_percentage' => $importLog->progress_percentage,
            'error_message' => $importLog->error_message,
            'error_details' => $importLog->error_details,
            'started_at' => $importLog->started_at?->toIso8601String(),
            'finished_at' => $importLog->finished_at?->toIso8601String(),
            'created_at' => $importLog->created_at?->toIso8601String(),
            'user' => $importLog->user,
        ]);
    }

    /**
     * Real-time polling status endpoint for import jobs.
     */
    public function status(ImportLog $importLog): JsonResponse
    {
        return response()->json([
            'id' => $importLog->id,
            'feature' => $importLog->feature,
            'feature_label' => $importLog->feature_label,
            'filename' => $importLog->filename,
            'status' => $importLog->status,
            'total_rows' => $importLog->total_rows,
            'processed_rows' => $importLog->processed_rows,
            'success_rows' => $importLog->success_rows,
            'failed_rows' => $importLog->failed_rows,
            'progress_percentage' => $importLog->progress_percentage,
            'error_message' => $importLog->error_message,
            'error_details' => $importLog->error_details,
            'started_at' => $importLog->started_at?->toIso8601String(),
            'finished_at' => $importLog->finished_at?->toIso8601String(),
        ]);
    }

    /**
     * Download Excel report for import errors.
     */
    public function downloadErrorReport(ImportLog $importLog): BinaryFileResponse
    {
        $feature = $importLog->feature ?: 'import';
        $timestamp = now()->format('Ymd_His');
        $filename = "laporan_error_{$feature}_{$timestamp}.xlsx";

        return Excel::download(new ImportErrorExport($importLog), $filename);
    }
}

