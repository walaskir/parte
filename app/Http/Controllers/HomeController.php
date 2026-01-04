<?php

namespace App\Http\Controllers;

use App\Models\DeathNotice;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        // Fetch 15 most recent death notices sorted by death_date (or created_at if missing)
        // Records with actual death_date take priority over records using created_at fallback
        $deathNotices = DeathNotice::query()
            ->with('media')
            ->orderByRaw('COALESCE(death_date, DATE(created_at)) DESC')
            ->orderByRaw('CASE WHEN death_date IS NULL THEN 1 ELSE 0 END ASC')
            ->limit(15)
            ->get();

        return view('welcome', compact('deathNotices'));
    }
}
