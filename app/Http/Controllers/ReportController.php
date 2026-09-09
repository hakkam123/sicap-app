<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Display the consumption report with filters and summary.
     */
    public function index(Request $request): Response
    {
        $areaId = $request->input('area_id');
        $machineId = $request->input('machine_id');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $perPage = (int) $request->input('per_page', 15);

        // Build base query
        $query = Consume::query()->with([
            'partNumber:id,pn_baan,description',
            'area:id,code,name',
            'machine:id,code,name',
        ]);

        if ($search) {
            $query->whereHas('partNumber', function ($q) use ($search) {
                $q->where('pn_baan', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($areaId) {
            $query->where('area_id', $areaId);
        }

        if ($machineId) {
            $query->where('machine_id', $machineId);
        }

        if ($dateFrom) {
            $query->whereDate('consumed_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('consumed_at', '<=', $dateTo);
        }

        // Summary totals across entire filtered dataset
        $rawTotals = (clone $query)
            ->selectRaw('SUM(ABS(quantity)) as total_qty, SUM(ABS(amount)) as total_amount')
            ->first();

        $summary = [
            'total_qty' => (int) abs($rawTotals->total_qty ?? 0),
            'total_amount' => (float) abs($rawTotals->total_amount ?? 0),
        ];

        // Paginated consumptions
        $consumptions = (clone $query)
            ->orderBy('consumed_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Area and dependent machine options
        $areas = Area::select('id', 'code', 'name')->orderBy('name')->get();
        $machines = $areaId
            ? Machine::where('area_id', $areaId)->select('id', 'code', 'name')->orderBy('name')->get()
            : [];

        return Inertia::render('Reports/Index', [
            'consumptions' => $consumptions,
            'areas'        => $areas,
            'machines'     => $machines,
            'filters'      => [
                'search'     => $search ?? '',
                'area_id'    => $areaId ?? '',
                'machine_id' => $machineId ?? '',
                'date_from'  => $dateFrom ?? '',
                'date_to'    => $dateTo ?? '',
                'per_page'   => $perPage,
            ],
            'summary'      => $summary,
        ]);
    }

    /**
     * Export the filtered consumption report to Excel or PDF.
     */
    public function export(Request $request)
    {
        $filters = $request->only(['search', 'area_id', 'machine_id', 'date_from', 'date_to']);
        $type = $request->input('type', 'excel');

        $dateFrom = $request->input('date_from') ?: 'semua';
        $dateTo = $request->input('date_to') ?: 'semua';
        $baseFilename = "laporan-konsumsi-{$dateFrom}-{$dateTo}";

        if ($type === 'pdf') {
            $query = Consume::query()->with([
                'partNumber:id,pn_baan,description',
                'area:id,code,name',
                'machine:id,code,name',
            ]);

            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->whereHas('partNumber', function ($q) use ($search) {
                    $q->where('pn_baan', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if (!empty($filters['area_id'])) {
                $query->where('area_id', $filters['area_id']);
            }

            if (!empty($filters['machine_id'])) {
                $query->where('machine_id', $filters['machine_id']);
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('consumed_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('consumed_at', '<=', $filters['date_to']);
            }

            $consumptions = $query->orderBy('consumed_at', 'desc')->get();

            $totalQty = (int) abs($consumptions->sum('quantity'));
            $totalAmount = (float) abs($consumptions->sum('amount'));

            $areaName = !empty($filters['area_id']) ? Area::find($filters['area_id'])?->name : null;
            $machineName = !empty($filters['machine_id']) ? Machine::find($filters['machine_id'])?->name : null;

            $pdf = Pdf::loadView('exports.report_pdf', [
                'consumptions' => $consumptions,
                'totalQty'     => $totalQty,
                'totalAmount'  => $totalAmount,
                'filters'      => $filters,
                'areaName'     => $areaName,
                'machineName'  => $machineName,
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$baseFilename}.pdf");
        }

        return Excel::download(new ReportExport($filters), "{$baseFilename}.xlsx");
    }
}

