<?php

namespace App\Http\Controllers;

use App\Http\Requests\AreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Area::query()->withCount('machines')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $areas = $query->paginate((int) $request->input('per_page', 10))->withQueryString();

        return Inertia::render('Area/Index', [
            'areas' => $areas,
            'filters' => [
                'search' => $request->input('search', ''),
                'per_page' => (int) $request->input('per_page', 10),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Area/Form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AreaRequest $request): RedirectResponse
    {
        Area::create($request->validated());

        return redirect()->route('areas.index')->with('success', 'Area berhasil ditambahkan');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Area $area): Response
    {
        return Inertia::render('Area/Form', [
            'area' => $area,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return redirect()->route('areas.index')->with('success', 'Area berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Area $area): RedirectResponse
    {
        $hasMachines = $area->machines()->exists();
        $hasConsumes = $area->consumes()->exists();

        if ($hasMachines || $hasConsumes) {
            return redirect()->back()->with('error', 'Area tidak dapat dihapus karena masih memiliki data terkait');
        }

        $area->delete();

        return redirect()->route('areas.index')->with('success', 'Area berhasil dihapus');
    }
}

