<?php

namespace App\Http\Controllers;

use App\Models\ImportLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportLogController extends Controller
{
    /**
     * Display a listing of import logs with status & feature filtering.
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

        $features = [
            ['value' => '', 'label' => 'Semua Fitur'],
            ['value' => 'consume', 'label' => 'Consume'],
            ['value' => 'part_number', 'label' => 'Part Number'],
            ['value' => 'area', 'label' => 'Area'],
            ['value' => 'machine', 'label' => 'Machine'],
            ['value' => 'mapping', 'label' => 'Mapping Part'],
            ['value' => 'user', 'label' => 'User'],
        ];

        return Inertia::render('ImportLog/Index', [
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
     * Display the specified import log and its detailed errors.
     */
    public function show(ImportLog $importLog): Response
    {
        $importLog->load('user:id,name,email');

        return Inertia::render('ImportLog/Show', [
            'log' => $importLog,
        ]);
    }
}
