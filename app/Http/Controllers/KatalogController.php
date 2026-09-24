<?php

namespace App\Http\Controllers;

use App\Models\PartNumber;
use Inertia\Inertia;
use Inertia\Response;

class KatalogController extends Controller
{
    /**
     * Display the public sparepart catalog page (without requiring authentication).
     * Data is retrieved from the 5-minute cache to minimize database load.
     */
    public function index(): Response
    {
        $parts = PartNumber::getCatalogData();

        return Inertia::render('Katalog/Index', [
            'parts' => $parts,
        ]);
    }
}

