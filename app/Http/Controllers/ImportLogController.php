<?php

namespace App\Http\Controllers;

use App\Models\ImportLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportLogController extends Controller
{
    /**
     * Display a listing of import logs with status filtering.
     */
    public function index(Request $request): Response
    {
        $query = ImportLog::with('user:id,name')->orderBy('created_at', 'DESC');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $logs = $query->paginate(20)->withQueryString();

        return Inertia::render('ImportLog/Index', [
            'logs' => $logs,
            'filters' => $request->only('status'),
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
