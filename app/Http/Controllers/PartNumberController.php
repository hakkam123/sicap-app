<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartNumberRequest;
use App\Models\Area;
use App\Models\PartNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartNumberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = PartNumber::query()
            ->with(['areas:id,code,name', 'machines:id,code,name,area_id'])
            ->withCount(['areas', 'machines'])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('pn_baan', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $partNumbers = $query->paginate((int) $request->input('per_page', 10))->withQueryString();

        // Ambil data area beserta mesin untuk keperluan modal mapping
        $areas = Area::with(['machines' => fn($q) => $q->whereNull('deleted_at')->orderBy('name')])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('PartNumber/Index', [
            'partNumbers' => $partNumbers,
            'areas'       => $areas,
            'filters'     => [
                'search'   => $request->input('search', ''),
                'per_page' => (int) $request->input('per_page', 10),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource (Fallback jika diakses langsung via URL).
     */
    public function create(): Response
    {
        return Inertia::render('PartNumber/Form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PartNumberRequest $request): RedirectResponse
    {
        $partNumber = PartNumber::create($request->validated());

        if ($request->has('area_ids')) {
            $partNumber->areas()->sync($request->input('area_ids', []));
        }

        if ($request->has('machine_ids')) {
            $partNumber->machines()->sync($request->input('machine_ids', []));
        }

        return redirect()->route('part-numbers.index')->with('success', 'Part Number berhasil ditambahkan');
    }

    /**
     * Show the form for editing the specified resource (Fallback).
     */
    public function edit(PartNumber $partNumber): Response
    {
        return Inertia::render('PartNumber/Form', [
            'partNumber' => $partNumber,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PartNumberRequest $request, PartNumber $partNumber): RedirectResponse
    {
        $partNumber->update($request->validated());

        if ($request->has('area_ids')) {
            $partNumber->areas()->sync($request->input('area_ids', []));
        }

        if ($request->has('machine_ids')) {
            $partNumber->machines()->sync($request->input('machine_ids', []));
        }

        return redirect()->route('part-numbers.index')->with('success', 'Part Number berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PartNumber $partNumber): RedirectResponse
    {
        $hasConsumes = $partNumber->consumes()->exists();

        if ($hasConsumes) {
            return redirect()->back()->with('error', 'Part Number tidak dapat dihapus karena masih memiliki data terkait');
        }

        $partNumber->delete();

        return redirect()->route('part-numbers.index')->with('success', 'Part Number berhasil dihapus');
    }
}


