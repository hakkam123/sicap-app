<?php

namespace App\Http\Controllers;

use App\Http\Requests\MachineRequest;
use App\Models\Area;
use App\Models\Machine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MachineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Machine::query()->with('area')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($areaId = $request->input('area_id')) {
            $query->where('area_id', $areaId);
        }

        $machines = $query->paginate((int) $request->input('per_page', 10))->withQueryString();
        $areas = Area::orderBy('name')->get(['id', 'code', 'name']);

        return Inertia::render('Machine/Index', [
            'machines' => $machines,
            'areas' => $areas,
            'filters' => [
                'search' => $request->input('search', ''),
                'area_id' => $request->input('area_id', ''),
                'per_page' => (int) $request->input('per_page', 10),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $areas = Area::orderBy('name')->get(['id', 'code', 'name']);

        return Inertia::render('Machine/Form', [
            'areas' => $areas,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MachineRequest $request): RedirectResponse
    {
        Machine::create($request->validated());

        return redirect()->route('machines.index')->with('success', 'Machine berhasil ditambahkan');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Machine $machine): Response
    {
        $machine->load('area');
        $areas = Area::orderBy('name')->get(['id', 'code', 'name']);

        return Inertia::render('Machine/Form', [
            'machine' => $machine,
            'areas' => $areas,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MachineRequest $request, Machine $machine): RedirectResponse
    {
        $machine->update($request->validated());

        return redirect()->route('machines.index')->with('success', 'Machine berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Machine $machine): RedirectResponse
    {
        $hasConsumes = $machine->consumes()->exists();

        if ($hasConsumes) {
            return redirect()->back()->with('error', 'Machine tidak dapat dihapus karena masih memiliki data terkait');
        }

        $machine->delete();

        return redirect()->route('machines.index')->with('success', 'Machine berhasil dihapus');
    }
}

