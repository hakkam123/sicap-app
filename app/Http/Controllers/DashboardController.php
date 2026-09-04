<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with consumption metrics and charts (positive values).
     */
    public function index(Request $request): Response
    {
        $areaId = $request->input('area_id');
        $machineId = $request->input('machine_id');
        
        // Determine default date range
        $defaultDateFrom = Consume::exists()
            ? Carbon::parse(Consume::min('consumed_at'))->toDateString()
            : now()->subDays(30)->toDateString();
        $defaultDateTo = now()->toDateString();

        $dateFrom = $request->input('date_from', $defaultDateFrom);
        $dateTo = $request->input('date_to', $defaultDateTo);

        // Build base query
        $query = Consume::query()
            ->when($areaId, fn($q, $v) => $q->where('area_id', $v))
            ->when($machineId, fn($q, $v) => $q->where('machine_id', $v))
            ->when($dateFrom, fn($q, $v) => $q->whereDate('consumed_at', '>=', $v))
            ->when($dateTo, fn($q, $v) => $q->whereDate('consumed_at', '<=', $v));

        // 1. Summary Metrics using SUM(ABS(...)) to handle both legacy negative and new positive numbers
        $rawTotals = (clone $query)
            ->selectRaw('SUM(ABS(quantity)) as total_qty, SUM(ABS(amount)) as total_amount, COUNT(*) as total_transactions')
            ->first();

        $summary = [
            'total_qty' => (int) ($rawTotals->total_qty ?? 0),
            'total_amount' => (float) ($rawTotals->total_amount ?? 0),
            'total_transactions' => (int) ($rawTotals->total_transactions ?? 0),
        ];

        // 2. Daily Chart Data (grouped by date, positive Qty & Amount)
        $chartData = (clone $query)
            ->selectRaw('CONVERT(varchar(10), consumed_at, 120) as [date], SUM(ABS(quantity)) as qty, SUM(ABS(amount)) as amount')
            ->groupByRaw('CONVERT(varchar(10), consumed_at, 120)')
            ->orderByRaw('CONVERT(varchar(10), consumed_at, 120) ASC')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'qty' => (int) abs($item->qty),
                    'amount' => (float) abs($item->amount),
                ];
            });

        // 3. Top Consumes by Part Number (Ordered by highest consumed Qty first)
        $topConsumes = (clone $query)
            ->select('part_number_id')
            ->selectRaw('SUM(ABS(quantity)) as total_qty, SUM(ABS(amount)) as total_amount, COUNT(*) as [count]')
            ->with('partNumber:id,pn_baan,description')
            ->groupBy('part_number_id')
            ->orderByRaw('SUM(ABS(quantity)) DESC')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'part_number_id' => $item->part_number_id,
                    'pn_baan' => $item->partNumber?->pn_baan ?? '-',
                    'description' => $item->partNumber?->description ?? '-',
                    'total_qty' => (int) abs($item->total_qty),
                    'total_amount' => (float) abs($item->total_amount),
                    'count' => (int) $item->count,
                ];
            });

        // 4. Filter dropdowns data
        $areas = Area::select('id', 'code', 'name')->orderBy('name')->get();
        $machines = $areaId
            ? Machine::where('area_id', $areaId)->select('id', 'code', 'name')->orderBy('name')->get()
            : [];

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'chartData' => $chartData,
            'topConsumes' => $topConsumes,
            'areas' => $areas,
            'machines' => $machines,
            'filters' => [
                'area_id' => $areaId ?? '',
                'machine_id' => $machineId ?? '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
