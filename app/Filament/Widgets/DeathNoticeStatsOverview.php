<?php

namespace App\Filament\Widgets;

use App\Models\DeathNotice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard widget displaying death notice statistics.
 *
 * Shows total count with source breakdown, monthly and yearly comparisons.
 */
class DeathNoticeStatsOverview extends BaseWidget
{
    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();
        $lastYear = $now->copy()->subYear();

        $stats = DeathNotice::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as this_month,
                SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as last_month,
                SUM(CASE WHEN YEAR(created_at) = ? THEN 1 ELSE 0 END) as this_year,
                SUM(CASE WHEN YEAR(created_at) = ? THEN 1 ELSE 0 END) as last_year
            ', [
                $now->month, $now->year,
                $lastMonth->month, $lastMonth->year,
                $now->year,
                $lastYear->year,
            ])
            ->first();

        $sourceBreakdown = DeathNotice::query()
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source')
            ->map(fn ($count, $source) => "{$source}: {$count}")
            ->implode(', ');

        return [
            Stat::make('Celkový počet parte', number_format($stats->total ?? 0, 0, ',', ' '))
                ->description($sourceBreakdown ?: 'Žádná data')
                ->color('primary'),
            Stat::make('Tento měsíc', number_format($stats->this_month ?? 0, 0, ',', ' '))
                ->description('Minulý měsíc: '.($stats->last_month ?? 0))
                ->color('success'),
            Stat::make('Tento rok', number_format($stats->this_year ?? 0, 0, ',', ' '))
                ->description('Minulý rok: '.($stats->last_year ?? 0))
                ->color('info'),
        ];
    }
}
