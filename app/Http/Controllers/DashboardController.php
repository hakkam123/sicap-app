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
        
        // Determine default date range (default 30 hari terakhir)
        $defaultDateFrom = now()->subDays(30)->toDateString();
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

        // 3. Top Consumes by Part Number (Ordered by highest total amount first)
        $topConsumes = (clone $query)
            ->select('part_number_id')
            ->selectRaw('SUM(ABS(amount)) as total_amount, SUM(ABS(quantity)) as total_qty, COUNT(*) as [count]')
            ->with('partNumber:id,pn_baan,description')
            ->groupBy('part_number_id')
            ->orderByRaw('SUM(ABS(amount)) DESC, SUM(ABS(quantity)) DESC')
            ->limit(10)
            ->get()
            ->values()
            ->map(function ($item, $index) {
                return [
                    'rank' => $index + 1,
                    'part_number_id' => $item->part_number_id,
                    'pn_baan' => $item->partNumber?->pn_baan ?? '-',
                    'description' => $item->partNumber?->description ?? '-',
                    'total_qty' => (int) abs($item->total_qty),
                    'total_amount' => (float) abs($item->total_amount),
                    'count' => (int) $item->count,
                ];
            });

        // 4. Area Breakdown (FA, SMT, Common) based on Part Number mapping
        // Query consumptions within date range (independent of area/machine filter)
        $consumptionsForArea = Consume::select('part_number_id')
            ->selectRaw('SUM(ABS(COALESCE(amount, 0))) as total_amount')
            ->when($dateFrom, fn($q) => $q->whereDate('consumed_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('consumed_at', '<=', $dateTo))
            ->groupBy('part_number_id')
            ->get();

        $partIds = $consumptionsForArea->pluck('part_number_id')->unique()->all();

        // Map area codes for each part number from area_part_number pivot
        $partAreas = !empty($partIds)
            ? DB::table('area_part_number')
                ->join('areas', 'areas.id', '=', 'area_part_number.area_id')
                ->whereIn('area_part_number.part_number_id', $partIds)
                ->whereNull('areas.deleted_at')
                ->select('area_part_number.part_number_id', 'areas.code')
                ->get()
                ->groupBy('part_number_id')
                ->map(fn($rows) => $rows->pluck('code')->map(fn($c) => strtoupper(trim($c)))->unique()->values()->all())
            : collect();

        $faAmount = 0.0;
        $smtAmount = 0.0;
        $commonAmount = 0.0;

        foreach ($consumptionsForArea as $c) {
            $codes = $partAreas->get($c->part_number_id, []);
            $hasFa = in_array('FA', $codes);
            $hasSmt = in_array('SMT', $codes);
            $amount = (float) abs($c->total_amount);

            if ($hasFa && $hasSmt) {
                $commonAmount += $amount;
            } elseif ($hasFa) {
                $faAmount += $amount;
            } elseif ($hasSmt) {
                $smtAmount += $amount;
            }
        }

        $areaConsumption = [
            'fa' => round($faAmount, 2),
            'smt' => round($smtAmount, 2),
            'common' => round($commonAmount, 2),
        ];

        // 5. Consumption by Area within filter period (Always include ALL active areas)
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
            ->sum(DB::raw('ABS(quantity)'));

        if ($unassignedQty > 0) {
            $unassignedAmount = Consume::whereNull('area_id')
                ->when($machineId, fn($q) => $q->where('machine_id', $machineId))
                ->when($dateFrom, fn($q) => $q->whereDate('consumed_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->whereDate('consumed_at', '<=', $dateTo))
                ->sum(DB::raw('ABS(amount)'));

            $unassignedCount = Consume::whereNull('area_id')
                ->when($machineId, fn($q) => $q->where('machine_id', $machineId))
                ->when($dateFrom, fn($q) => $q->whereDate('consumed_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->whereDate('consumed_at', '<=', $dateTo))
                ->count();

            $byArea->push([
                'area_id' => null,
                'area_name' => 'Consume Tanpa Area (Unassigned)',
                'area_code' => 'UNASSIGNED',
                'total_qty' => (int) abs($unassignedQty),
                'total_amount' => (float) abs($unassignedAmount),
                'total_count' => (int) $unassignedCount,
            ]);

            // Re-sort by total_qty desc
            $byArea = $byArea->sortByDesc('total_qty')->values();
        }

        // 6. Filter dropdowns data
        $areas = Area::select('id', 'code', 'name')->orderBy('name')->get();
        $machines = $areaId
            ? Machine::where('area_id', $areaId)->select('id', 'code', 'name')->orderBy('name')->get()
            : [];

        return Inertia::render('Dashboard', [
            'summary' => $summary,
            'chartData' => $chartData,
            'trendData' => $chartData,
            'topParts' => $topConsumes,
            'topConsumes' => $topConsumes,
            'areaConsumption' => $areaConsumption,
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
            'page'      => 'nullable|integer|min:1',
            'per_page'  => 'nullable|integer|min:5|max:100',
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

        // Summary totals across ALL matched records for this drill-down
        $rawTotals = (clone $query)
            ->selectRaw('SUM(ABS(quantity)) as total_qty, SUM(ABS(amount)) as total_amount, COUNT(*) as total_count')
            ->first();

        $perPage = (int) $request->input('per_page', 15);
        $paginated = $query->orderByDesc('consumed_at')->paginate($perPage);

        $rows = collect($paginated->items())->map(fn($c) => [
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
            'total_qty'    => (int) abs($rawTotals->total_qty ?? 0),
            'total_amount' => (float) abs($rawTotals->total_amount ?? 0),
            'pagination'   => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
        ]);
    }
}
