<?php

namespace App\Http\Controllers;

use App\Models\ImportLog;
use App\Models\SystemErrorLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ErrorMonitoringController extends Controller
{
    /**
     * Display system error monitoring dashboard, KPI cards, and log listing.
     */
    public function index(Request $request): Response
    {
        $baseQuery = SystemErrorLog::query();

        // 1. KPI Statistics
        $today = Carbon::today();
        $stats = [
            'total_errors'      => (clone $baseQuery)->count(),
            'errors_today'      => (clone $baseQuery)->whereDate('created_at', $today)->count(),
            'critical_errors'   => (clone $baseQuery)->where(function ($q) {
                $q->where('status_code', '>=', 500)
                  ->orWhere('severity', 'critical');
            })->count(),
            'not_found_errors'  => (clone $baseQuery)->where('status_code', 404)->count(),
            'unresolved_errors' => (clone $baseQuery)->where('status', 'unresolved')->count(),
        ];

        // 2. Filter query
        $query = SystemErrorLog::with(['user:id,name,email', 'resolver:id,name'])
            ->latest('created_at');

        if ($feature = $request->input('feature')) {
            $query->where('feature', $feature);
        }

        if ($severity = $request->input('severity')) {
            $query->where('severity', $severity);
        }

        if ($statusCode = $request->input('status_code')) {
            $query->where('status_code', (int) $statusCode);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%")
                  ->orWhere('exception_class', 'like', "%{$search}%")
                  ->orWhere('error_type', 'like', "%{$search}%")
                  ->orWhere('user_ip', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
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

        // Features list for filter
        $features = [
            ['value' => '', 'label' => 'Semua Fitur'],
            ['value' => 'consume', 'label' => 'Consume Transaksi'],
            ['value' => 'part_number', 'label' => 'Part Number'],
            ['value' => 'area', 'label' => 'Area'],
            ['value' => 'machine', 'label' => 'Machine'],
            ['value' => 'mapping', 'label' => 'Mapping Part'],
            ['value' => 'user', 'label' => 'User Management'],
            ['value' => 'report', 'label' => 'Laporan'],
            ['value' => 'sync_api', 'label' => 'Sync API Eksternal'],
            ['value' => 'auth', 'label' => 'Autentikasi & Keamanan'],
            ['value' => 'system', 'label' => 'System Core'],
        ];

        // Severity options
        $severities = [
            ['value' => '', 'label' => 'Semua Severity'],
            ['value' => 'critical', 'label' => 'Critical (500 / Fatal)'],
            ['value' => 'error', 'label' => 'Error'],
            ['value' => 'warning', 'label' => 'Warning (404 / 403)'],
            ['value' => 'info', 'label' => 'Info (422 Validasi)'],
        ];

        // Status code quick filters
        $statusCodes = [
            ['value' => '', 'label' => 'Semua Status Code'],
            ['value' => '500', 'label' => '500 - Server Error'],
            ['value' => '404', 'label' => '404 - Not Found'],
            ['value' => '403', 'label' => '403 - Forbidden'],
            ['value' => '401', 'label' => '401 - Unauthorized'],
            ['value' => '422', 'label' => '422 - Validation Error'],
            ['value' => '429', 'label' => '429 - Too Many Requests'],
        ];

        return Inertia::render('ErrorMonitoring/Index', [
            'logs' => $logs,
            'stats' => $stats,
            'features' => $features,
            'severities' => $severities,
            'statusCodes' => $statusCodes,
            'filters' => [
                'feature'     => $request->input('feature', ''),
                'severity'    => $request->input('severity', ''),
                'status_code' => $request->input('status_code', ''),
                'status'      => $request->input('status', ''),
                'search'      => $request->input('search', ''),
                'date_from'   => $request->input('date_from', ''),
                'date_to'     => $request->input('date_to', ''),
                'per_page'    => $perPage,
            ],
        ]);
    }

    /**
     * Return single error log detail as JSON.
     */
    public function show(SystemErrorLog $errorLog): JsonResponse
    {
        $errorLog->load(['user:id,name,email', 'resolver:id,name,email']);

        $payloadData = null;
        if ($errorLog->request_payload) {
            $payloadData = json_decode($errorLog->request_payload, true) ?: $errorLog->request_payload;
        }

        return response()->json([
            'id'               => $errorLog->id,
            'error_type'       => $errorLog->error_type,
            'status_code'      => $errorLog->status_code,
            'severity'         => $errorLog->severity,
            'feature'          => $errorLog->feature,
            'feature_label'    => $errorLog->feature_label,
            'message'          => $errorLog->message,
            'exception_class'  => $errorLog->exception_class,
            'file'             => $errorLog->file,
            'line'             => $errorLog->line,
            'url'              => $errorLog->url,
            'method'           => $errorLog->method,
            'request_payload'  => $payloadData,
            'stack_trace'      => $errorLog->stack_trace,
            'user'             => $errorLog->user,
            'user_ip'          => $errorLog->user_ip,
            'user_agent'       => $errorLog->user_agent,
            'status'           => $errorLog->status,
            'resolved_at'      => $errorLog->resolved_at?->setTimezone('Asia/Jakarta')->format('d M Y, H:i:s'),
            'resolver'         => $errorLog->resolver,
            'resolution_notes' => $errorLog->resolution_notes,
            'created_at'       => $errorLog->created_at?->setTimezone('Asia/Jakarta')->format('d M Y, H:i:s'),
        ]);
    }

    /**
     * Mark an error log as resolved (or toggle back to unresolved).
     */
    public function resolve(Request $request, SystemErrorLog $errorLog): RedirectResponse
    {
        if ($errorLog->status === 'resolved') {
            $errorLog->update([
                'status'           => 'unresolved',
                'resolved_at'      => null,
                'resolved_by'      => null,
                'resolution_notes' => null,
            ]);
            $msg = 'Status error dikembalikan ke Belum Ditangani.';
        } else {
            $errorLog->update([
                'status'           => 'resolved',
                'resolved_at'      => now(),
                'resolved_by'      => Auth::id(),
                'resolution_notes' => $request->input('notes', 'Ditandai selesai oleh admin.'),
            ]);
            $msg = 'Error berhasil ditandai sebagai Selesai Ditangani.';
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Mark all unresolved error logs as resolved.
     */
    public function resolveAll(Request $request): RedirectResponse
    {
        $updated = SystemErrorLog::where('status', 'unresolved')->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', "Sebanyak {$updated} log error berhasil ditandai sebagai Selesai.");
    }

    /**
     * Delete a single error log.
     */
    public function destroy(SystemErrorLog $errorLog): RedirectResponse
    {
        $errorLog->delete();

        return redirect()->back()->with('success', 'Log error berhasil dihapus.');
    }

    /**
     * Clear all error logs.
     */
    public function clearAll(): RedirectResponse
    {
        SystemErrorLog::query()->delete();

        return redirect()->back()->with('success', 'Semua log error sistem berhasil dibersihkan.');
    }

    /**
     * Real-time polling status endpoint for import jobs (kept for backwards compatibility).
     */
    public function status(ImportLog $importLog): JsonResponse
    {
        return response()->json([
            'id'                  => $importLog->id,
            'feature'             => $importLog->feature,
            'feature_label'       => $importLog->feature_label,
            'filename'            => $importLog->filename,
            'status'              => $importLog->status,
            'total_rows'          => $importLog->total_rows,
            'processed_rows'      => $importLog->processed_rows,
            'success_rows'        => $importLog->success_rows,
            'failed_rows'         => $importLog->failed_rows,
            'progress_percentage' => $importLog->progress_percentage,
            'error_message'       => $importLog->error_message,
            'error_details'       => $importLog->error_details,
            'started_at'          => $importLog->started_at?->toIso8601String(),
            'finished_at'         => $importLog->finished_at?->toIso8601String(),
        ]);
    }
}
