<?php

namespace App\Filament\Resources\DeathNoticeResource\Pages;

use App\Filament\Resources\DeathNoticeResource;
use App\Filament\Resources\DeathNoticeResource\Widgets\DeathNoticeFilteredStats;
use App\Models\FuneralService;
use App\Services\DeathNoticeService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListDeathNotices extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = DeathNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Stáhnout nová parte')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->form([
                    CheckboxList::make('sources')
                        ->label('Zdroje ke stažení')
                        ->options(fn () => FuneralService::where('active', true)
                            ->pluck('name', 'slug')
                            ->toArray())
                        ->default(fn () => FuneralService::where('active', true)
                            ->pluck('slug')
                            ->toArray())
                        ->columns(2)
                        ->required(),
                ])
                ->action(function (array $data, DeathNoticeService $service): void {
                    $sources = $data['sources'];

                    try {
                        $results = $service->downloadNotices($sources);

                        if ($results['errors'] > 0) {
                            Notification::make()
                                ->title('Stahování dokončeno s chybami')
                                ->body(sprintf(
                                    "Nová: %d | Duplicity: %d | Chyby: %d\nZkontrolujte log pro detaily.",
                                    $results['new'],
                                    $results['duplicates'],
                                    $results['errors']
                                ))
                                ->warning()
                                ->persistent()
                                ->send();
                        } elseif ($results['new'] > 0) {
                            Notification::make()
                                ->title('Stahování dokončeno')
                                ->body(sprintf(
                                    'Nová: %d | Duplicity: %d',
                                    $results['new'],
                                    $results['duplicates']
                                ))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Žádná nová parte')
                                ->body(sprintf('Duplicity: %d', $results['duplicates']))
                                ->info()
                                ->send();
                        }
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Chyba při stahování')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                })
                ->modalHeading('Stáhnout nová parte')
                ->modalSubmitActionLabel('Stáhnout')
                ->modalWidth('md'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DeathNoticeFilteredStats::class,
        ];
    }
}
