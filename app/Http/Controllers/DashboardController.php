<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Consume;
use App\Models\Machine;
use App\Models\PartNumber;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // 1. Summary Metrics using SQL aggregation (SUM(ABS(...)))
        $rawTotals = Consume::filteredByRequest($areaId, $machineId, $dateFrom, $dateTo)
            ->selectRaw('SUM(ABS(quantity)) as total_qty, SUM(ABS(amount)) as total_amount, COUNT(*) as total_transactions')
            ->first();

        $summary = [
            'total_qty' => (int) ($rawTotals->total_qty ?? 0),
            'total_amount' => (float) ($rawTotals->total_amount ?? 0),
            'total_transactions' => (int) ($rawTotals->total_transactions ?? 0),
        ];

        // 2. Daily Chart Data (Clean fresh query using scope, database-level grouping)
        $driver = DB::connection()->getDriverName();
        $dateFormat = $driver === 'sqlsrv'
            ? 'CONVERT(varchar(10), consumed_at, 120)'
            : "strftime('%Y-%m-%d', consumed_at)";

        $chartData = Consume::filteredByRequest($areaId, $machineId, $dateFrom, $dateTo)
            ->selectRaw("{$dateFormat} as [date], SUM(ABS(quantity)) as qty, SUM(ABS(amount)) as amount")
            ->groupByRaw($dateFormat)
            ->orderByRaw("{$dateFormat} ASC")
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'qty' => (int) abs($item->qty),
                    'amount' => (float) abs($item->amount),
                ];
            });

        // 3. Top Consumes by Part Number (Ordered by highest total amount first)
        $topConsumes = Consume::filteredByRequest($areaId, $machineId, $dateFrom, $dateTo)
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
        $consumptionsForArea = Consume::select('part_number_id')
            ->selectRaw('SUM(ABS(COALESCE(amount, 0))) as total_amount')
            ->when($dateFrom, fn($q) => $q->whereDate('consumed_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('consumed_at', '<=', $dateTo))
            ->groupBy('part_number_id')
            ->get();

        $partIds = $consumptionsForArea->pluck('part_number_id')->unique()->all();

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

        // 5. Consumption by Area within filter period (SQL Aggregate Optimization)
        $byArea = $this->buildByAreaConsumption($machineId, $dateFrom, $dateTo);

        // 6. Filter dropdowns data & Last sync timestamp
        $areas = Area::select('id', 'code', 'name')->orderBy('name')->get();
        $machines = [];
        if ($areaId === 'common') {
            $machines = Machine::with('area:id,code,name')
                ->whereHas('area', fn($q) => $q->whereIn('code', ['FA', 'SMT']))
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'area_id', 'code', 'name']);
        } elseif ($areaId) {
            $machines = Machine::where('area_id', $areaId)->select('id', 'area_id', 'code', 'name')->orderBy('name')->get();
        }

        $rawLastSync = Cache::get('last_api_sync_at')
            ?? Consume::where('source', 'api')->latest('created_at')->value('created_at')
            ?? Consume::latest('created_at')->value('created_at');

        $lastSyncFormatted = $rawLastSync
            ? Carbon::parse($rawLastSync)->setTimezone('Asia/Jakarta')->format('H:i:s \W\I\B, d/m/Y')
            : null;

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
            'lastSyncAt' => $lastSyncFormatted,
            'filters' => [
                'area_id' => $areaId ?? '',
                'machine_id' => $machineId ?? '',
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
        ]);
    }

    /**
     * Build aggregated consumption grouped by Area directly via SQL Server queries.
     * Replaces in-memory PHP loops over full collections to eliminate Tempdb spills and RAM bloat.
     */
    private function buildByAreaConsumption(?string $machineId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $activeAreas = Area::whereNull('deleted_at')->orderBy('name')->get();

        // 1. Direct assigned consumes (consumes.area_id IS NOT NULL)
        $directAgg = Consume::query()
            ->whereNotNull('consumes.area_id')
            ->when($machineId, fn($q, $v) => $q->where('consumes.machine_id', $v))
            ->when($dateFrom, fn($q, $v) => $q->whereDate('consumes.consumed_at', '>=', $v))
            ->when($dateTo, fn($q, $v) => $q->whereDate('consumes.consumed_at', '<=', $v))
            ->select('consumes.area_id')
            ->selectRaw('SUM(ABS(consumes.quantity)) as total_qty, SUM(ABS(COALESCE(consumes.amount, 0))) as total_amount, COUNT(*) as total_count')
            ->groupBy('consumes.area_id')
            ->get();

        // 2. Mapped via Part Number (when consumes.area_id IS NULL)
        $mappedAgg = Consume::query()
            ->join('area_part_number', 'area_part_number.part_number_id', '=', 'consumes.part_number_id')
            ->whereNull('consumes.area_id')
            ->when($machineId, fn($q, $v) => $q->where('consumes.machine_id', $v))
            ->when($dateFrom, fn($q, $v) => $q->whereDate('consumes.consumed_at', '>=', $v))
            ->when($dateTo, fn($q, $v) => $q->whereDate('consumes.consumed_at', '<=', $v))
            ->select('area_part_number.area_id')
            ->selectRaw('SUM(ABS(consumes.quantity)) as total_qty, SUM(ABS(COALESCE(consumes.amount, 0))) as total_amount, COUNT(*) as total_count')
            ->groupBy('area_part_number.area_id')
            ->get();

        // 3. Unassigned consumes (consumes.area_id IS NULL AND no area_part_number mapping)
        $unassignedAgg = Consume::query()
            ->whereNull('consumes.area_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('area_part_number')
                      ->whereColumn('area_part_number.part_number_id', 'consumes.part_number_id');
            })
            ->when($machineId, fn($q, $v) => $q->where('consumes.machine_id', $v))
            ->when($dateFrom, fn($q, $v) => $q->whereDate('consumes.consumed_at', '>=', $v))
            ->when($dateTo, fn($q, $v) => $q->whereDate('consumes.consumed_at', '<=', $v))
            ->selectRaw('SUM(ABS(consumes.quantity)) as total_qty, SUM(ABS(COALESCE(consumes.amount, 0))) as total_amount, COUNT(*) as total_count')
            ->first();

        $areaStats = [];
        foreach ($activeAreas as $area) {
            $areaStats[$area->id] = [
                'area_id' => $area->id,
                'area_name' => $area->code ?: Area::abbreviate($area->name),
                'area_code' => $area->code,
                'total_qty' => 0,
                'total_amount' => 0.0,
                'total_count' => 0,
            ];
        }

        foreach ($directAgg as $row) {
            if (isset($areaStats[$row->area_id])) {
                $areaStats[$row->area_id]['total_qty'] += (int) abs($row->total_qty);
                $areaStats[$row->area_id]['total_amount'] += (float) abs($row->total_amount);
                $areaStats[$row->area_id]['total_count'] += (int) $row->total_count;
            }
        }

        foreach ($mappedAgg as $row) {
            if (isset($areaStats[$row->area_id])) {
                $areaStats[$row->area_id]['total_qty'] += (int) abs($row->total_qty);
                $areaStats[$row->area_id]['total_amount'] += (float) abs($row->total_amount);
                $areaStats[$row->area_id]['total_count'] += (int) $row->total_count;
            }
        }

        $byArea = collect(array_values($areaStats))->sortByDesc('total_amount')->values();

        $unassignedCount = (int) ($unassignedAgg->total_count ?? 0);
        if ($unassignedCount > 0) {
            $byArea->push([
                'area_id' => null,
                'area_name' => 'Consume Tanpa Area (Unassigned)',
                'area_code' => 'UNASSIGNED',
                'total_qty' => (int) abs($unassignedAgg->total_qty ?? 0),
                'total_amount' => (float) abs($unassignedAgg->total_amount ?? 0),
                'total_count' => $unassignedCount,
            ]);
        }

        return $byArea;
    }

    /**
     * Get drill-down transactions detail for a specific area or part number.
     */
    public function drillDown(Request $request): JsonResponse
    {
        $request->validate([
            'type'      => 'required|in:area,part,unassigned,category,fa,smt,common',
            'id'        => 'required|string',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'page'      => 'nullable|integer|min:1',
            'per_page'  => 'nullable|integer|min:5|max:100',
        ]);

        $query = Consume::with([
            'partNumber:id,pn_baan,description',
            'partNumber.areas:id,code,name',
            'partNumber.machines:id,area_id,code,name',
            'partNumber.machines.area:id,code,name',
            'area:id,code,name',
            'machine:id,area_id,code,name',
            'machine.area:id,code,name',
            'creator:id,name',
        ])
            ->when($request->date_from, fn($q) => $q->whereDate('consumed_at', '>=', $request->date_from))
            ->when($request->date_to,   fn($q) => $q->whereDate('consumed_at', '<=', $request->date_to));

        if ($request->type === 'category' || in_array($request->type, ['fa', 'smt', 'common'])) {
            $category = strtolower($request->type === 'category' ? $request->id : $request->type);

            $partAreas = DB::table('area_part_number')
                ->join('areas', 'areas.id', '=', 'area_part_number.area_id')
                ->whereNull('areas.deleted_at')
                ->select('area_part_number.part_number_id', 'areas.code')
                ->get()
                ->groupBy('part_number_id')
                ->map(fn($rows) => $rows->pluck('code')->map(fn($c) => strtoupper(trim($c)))->unique()->values()->all());

            $matchedPartIds = [];
            foreach ($partAreas as $partId => $codes) {
                $hasFa = in_array('FA', $codes);
                $hasSmt = in_array('SMT', $codes);

                if ($category === 'common' && $hasFa && $hasSmt) {
                    $matchedPartIds[] = $partId;
                } elseif ($category === 'fa' && $hasFa && !$hasSmt) {
                    $matchedPartIds[] = $partId;
                } elseif ($category === 'smt' && $hasSmt && !$hasFa) {
                    $matchedPartIds[] = $partId;
                }
            }

            $query->whereIn('part_number_id', $matchedPartIds);
            $categoryLabel = match ($category) {
                'fa' => 'FA (Fabrication)',
                'smt' => 'SMT (Surface Mount)',
                'common' => 'Common (FA & SMT)',
                default => strtoupper($category),
            };
            $title = "Konsumsi Area — {$categoryLabel}";
        } elseif ($request->type === 'unassigned') {
            $query->whereNull('area_id');
            $title = 'Consume Tanpa Area (Unassigned)';
        } elseif ($request->type === 'area') {
            $areaId = $request->id;
            $query->where(function ($sub) use ($areaId) {
                $sub->where('consumes.area_id', $areaId)
                    ->orWhereExists(function ($ex) use ($areaId) {
                        $ex->select(DB::raw(1))
                           ->from('area_part_number')
                           ->whereColumn('area_part_number.part_number_id', 'consumes.part_number_id')
                           ->where('area_part_number.area_id', $areaId);
                    });
            });
            $foundArea = Area::find($request->id);
            $title = $foundArea ? ($foundArea->code ?: Area::abbreviate($foundArea->name)) : 'Area';
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
            'area'        => $c->display_area,
            'machine'     => $c->display_machine,
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
