<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // 4. Consumption by Area within filter period (Always include ALL active areas)
        $byArea = DB::table('areas')
            ->leftJoin('consumes', function ($join) use ($machineId, $dateFrom, $dateTo) {
                $join->on('consumes.area_id', '=', 'areas.id')
                    ->when($machineId, fn($j) => $j->where('consumes.machine_id', $machineId))
                    ->when($dateFrom, fn($j) => $j->whereDate('consumes.consumed_at', '>=', $dateFrom))
                    ->when($dateTo, fn($j) => $j->whereDate('consumes.consumed_at', '<=', $dateTo));
            })
            ->whereNull('areas.deleted_at')
            ->groupBy('areas.id', 'areas.name', 'areas.code')
            ->select([
                'areas.id as area_id',
                'areas.name as area_name',
                'areas.code as area_code',
                DB::raw('COALESCE(SUM(ABS(consumes.quantity)), 0) as total_qty'),
                DB::raw('COALESCE(SUM(ABS(consumes.amount)), 0) as total_amount'),
                DB::raw('COUNT(consumes.id) as total_count'),
            ])
            ->orderByDesc('total_qty')
            ->orderBy('areas.name', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'area_id' => $item->area_id,
                    'area_name' => $item->area_name,
                    'area_code' => $item->area_code,
                    'total_qty' => (int) abs($item->total_qty),
                    'total_amount' => (float) abs($item->total_amount),
                    'total_count' => (int) $item->total_count,
                ];
            });

        // Data consume tanpa area (NULL)
        $unassignedQty = Consume::whereNull('area_id')
            ->when($machineId, fn($q) => $q->where('machine_id', $machineId))
            ->when($dateFrom, fn($q) => $q->whereDate('consumed_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('consumed_at', '<=', $dateTo))
            ->selectRaw('SUM(ABS(quantity)) as total_qty, SUM(ABS(amount)) as total_amount, COUNT(*) as total_count')
            ->first();

        // Append ke collection byArea jika ada data unassigned
        if ($unassignedQty && $unassignedQty->total_count > 0) {
            $byArea->push([
                'area_id'      => null,
                'area_name'    => 'Tidak Diketahui',
                'area_code'    => '—',
                'total_qty'    => (int) abs($unassignedQty->total_qty ?? 0),
                'total_amount' => (float) abs($unassignedQty->total_amount ?? 0),
                'total_count'  => (int) ($unassignedQty->total_count ?? 0),
            ]);
            // Re-sort by total_qty desc
            $byArea = $byArea->sortByDesc('total_qty')->values();
        }

        // 5. Filter dropdowns data
        $areas = Area::select('id', 'code', 'name')->orderBy('name')->get();
        $machines = $areaId
            ? Machine::where('area_id', $areaId)->select('id', 'code', 'name')->orderBy('name')->get()
            : [];

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'chartData' => $chartData,
            'topConsumes' => $topConsumes,
            'byArea' => $byArea,
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

    /**
     * Get drill-down transactions detail for a specific area or part number.
     */
    public function drillDown(Request $request): JsonResponse
    {
        $request->validate([
            'type'      => 'required|in:area,part,unassigned',
            'id'        => 'required|string',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $query = Consume::with(['partNumber', 'area', 'machine', 'creator'])
            ->when($request->date_from, fn($q) => $q->whereDate('consumed_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('consumed_at', '<=', $request->date_to));

        if ($request->type === 'unassigned') {
            $query->whereNull('area_id');
            $title = 'Consume Tanpa Area (Unassigned)';
        } elseif ($request->type === 'area') {
            $query->where('area_id', $request->id);
            $title = Area::find($request->id)?->name ?? 'Area';
        } else {
            $query->where('part_number_id', $request->id);
            $pn = PartNumber::find($request->id);
            $title = $pn ? "{$pn->pn_baan} — {$pn->description}" : 'Part Number';
        }

        $rows = $query->orderByDesc('consumed_at')->limit(50)->get()->map(fn($c) => [
            'date'        => $c->consumed_at?->format('d M Y'),
            'pn_baan'     => $c->partNumber?->pn_baan,
            'description' => $c->partNumber?->description,
            'area'        => $c->area?->name,
            'machine'     => $c->machine?->name,
            'qty'         => abs($c->quantity),
            'amount'      => abs($c->amount ?? 0),
            'source'      => $c->source,
            'input_by'    => $c->creator?->name ?? '-',
        ]);

        return response()->json([
            'title'        => $title,
            'rows'         => $rows,
            'total_qty'    => $rows->sum('qty'),
            'total_amount' => $rows->sum('amount'),
        ]);
    }
}
