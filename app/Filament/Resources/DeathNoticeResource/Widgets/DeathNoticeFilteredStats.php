<?php

namespace App\Filament\Resources\DeathNoticeResource\Widgets;

use App\Filament\Resources\DeathNoticeResource\Pages\ListDeathNotices;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stats widget for the DeathNotice resource list page.
 *
 * Displays filtered statistics that respond to table filters,
 * showing count, monthly and yearly breakdowns.
 */
class DeathNoticeFilteredStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    public function updatedTableFilters(): void
    {
        unset($this->tablePage);
    }

    protected function getTablePage(): string
    {
        return ListDeathNotices::class;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $baseQuery = $this->getPageTableQuery()->reorder()->limit(null);
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();
        $lastYear = $now->copy()->subYear();

        $stats = (clone $baseQuery)
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

        $sourceBreakdown = (clone $baseQuery)
            ->reorder()
            ->select('source', DB::raw('COUNT(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source')
            ->map(fn ($count, $source) => "{$source}: {$count}")
            ->implode(', ');

        return [
            Stat::make('Filtrované záznamy', number_format($stats->total ?? 0, 0, ',', ' '))
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
