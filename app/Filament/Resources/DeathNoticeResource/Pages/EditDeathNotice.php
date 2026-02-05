<?php

namespace App\Filament\Resources\DeathNoticeResource\Pages;

use App\Filament\Resources\DeathNoticeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDeathNotice extends EditRecord
{
    protected static string $resource = DeathNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_source')
                ->label('Zobrazit zdroj')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (): ?string => $this->record->source_url)
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->record->source_url)),
        ];
    }
}
