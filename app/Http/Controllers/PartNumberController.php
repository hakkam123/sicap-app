<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartNumberRequest;
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
        $query = PartNumber::query()->withCount(['areas', 'machines'])->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('pn_baan', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $partNumbers = $query->paginate((int) $request->input('per_page', 10))->withQueryString();

        return Inertia::render('PartNumber/Index', [
            'partNumbers' => $partNumbers,
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
        return Inertia::render('PartNumber/Form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PartNumberRequest $request): RedirectResponse
    {
        PartNumber::create($request->validated());

        return redirect()->route('part-numbers.index')->with('success', 'Part Number berhasil ditambahkan');
    }

    /**
     * Show the form for editing the specified resource.
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

