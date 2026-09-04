<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsumeRequest;
use App\Models\Area;
use App\Models\Consume;
use App\Models\ImportLog;
use App\Models\Machine;
use App\Models\PartNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ConsumeController extends Controller
{
    /**
     * Display the unified consume listing, filters, modals, and recent import logs.
     */
    public function index(Request $request): Response
    {
        $query = Consume::with([
            'partNumber:id,pn_baan,description,price_per_unit',
            'area:id,code,name',
            'machine:id,code,name',
            'creator:id,name',
        ]);

        // 1. Search Filter (by Part Number PN or Description)
        if ($search = $request->input('search')) {
            $query->whereHas('partNumber', function ($partQuery) use ($search) {
                $partQuery->where('pn_baan', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 2. Area Filter
        if ($areaId = $request->input('area_id')) {
            $query->where('area_id', $areaId);
        }

        // 3. Machine Filter
        if ($machineId = $request->input('machine_id')) {
            $query->where('machine_id', $machineId);
        }

        // 4. Date Range Filters
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('consumed_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('consumed_at', '<=', $dateTo);
        }

        $consumes = $query->orderBy('consumed_at', 'DESC')->paginate((int) $request->input('per_page', 10))->withQueryString();

        $areas = Area::select('id', 'code', 'name')->whereNull('deleted_at')->orderBy('name')->get();
        $importLogs = ImportLog::with('user:id,name')->orderBy('created_at', 'DESC')->limit(10)->get();
        $partNumbers = PartNumber::select('id', 'pn_baan', 'description', 'price_per_unit')
            ->whereNull('deleted_at')
            ->orderBy('pn_baan')
            ->get();

        return Inertia::render('Consume/Index', [
            'consumes' => $consumes,
            'areas' => $areas,
            'filters' => array_merge($request->only(['search', 'area_id', 'machine_id', 'date_from', 'date_to']), ['per_page' => (int) $request->input('per_page', 10)]),
            'importLogs' => $importLogs,
            'partNumbers' => $partNumbers,
        ]);
    }

    /**
     * Get machines filtered by area ID via AJAX.
     */
    public function getMachinesByArea(Request $request): JsonResponse
    {
        $machines = Machine::where('area_id', $request->area_id)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json($machines);
    }

    /**
     * Store a newly created consume record in storage (positive quantity).
     */
    public function store(ConsumeRequest $request): RedirectResponse
    {
        $amount = $request->input('amount');
        $qty = (int) $request->quantity;

        // Auto-calculate amount if omitted
        if ($amount === null || $amount === '') {
            $part = PartNumber::find($request->part_number_id);
            if ($part && $part->price_per_unit !== null) {
                $amount = (float) $part->price_per_unit * $qty;
            } else {
                $amount = null;
            }
        }

        Consume::create([
            'part_number_id' => $request->part_number_id,
            'area_id' => $request->area_id,
            'machine_id' => $request->machine_id,
            'quantity' => $qty,
            'amount' => $amount,
            'consumed_at' => $request->consumed_at,
            'source' => 'manual',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('consume.index')->with('success', 'Data consume berhasil ditambahkan');
    }

    /**
     * Remove the specified consume record from storage.
     */
    public function destroy(Consume $consume): RedirectResponse
    {
        $consume->delete();

        return redirect()->route('consume.index')->with('success', 'Data consume berhasil dihapus');
    }
}
