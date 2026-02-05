<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeathNoticeResource\Pages;
use App\Jobs\ExtractDeathDateAndAnnouncementJob;
use App\Models\DeathNotice;
use App\Services\VisionOcrService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class DeathNoticeResource extends Resource
{
    protected static ?string $model = DeathNotice::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'Parte';

    protected static ?string $pluralModelLabel = 'Parte';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Základní údaje')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Celé jméno')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('death_date')
                            ->label('Datum úmrtí'),
                        DatePicker::make('funeral_date')
                            ->label('Datum pohřbu'),
                        Placeholder::make('created_at_display')
                            ->label('Vytvořeno')
                            ->content(fn (?DeathNotice $record): string => $record?->created_at?->format('j. n. Y H:i') ?? '-'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Texty')
                    ->schema([
                        Textarea::make('opening_quote')
                            ->label('Úvodní citát')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('announcement_text')
                            ->label('Text oznámení')
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Zdroj')
                    ->schema([
                        TextInput::make('hash')
                            ->label('Hash')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('source')
                            ->label('Zdroj')
                            ->disabled()
                            ->dehydrated(false),
                        Placeholder::make('source_url_link')
                            ->label('URL zdroje')
                            ->content(fn (?DeathNotice $record): HtmlString => $record?->source_url
                                ? new HtmlString('<a href="'.e($record->source_url).'" target="_blank" class="text-primary-600 hover:underline">'.e($record->source_url).'</a>')
                                : new HtmlString('<span class="text-gray-400">-</span>'))
                            ->columnSpan(2),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Soubory')
                    ->schema([
                        Placeholder::make('pdf_preview')
                            ->label('PDF')
                            ->content(function (?DeathNotice $record): HtmlString {
                                $media = $record?->getFirstMedia('pdf');
                                if ($media) {
                                    $thumbUrl = $media->getUrl('thumb');
                                    $fullUrl = $media->getUrl();

                                    return new HtmlString('<a href="'.e($fullUrl).'" target="_blank" class="block"><img src="'.e($thumbUrl).'" alt="PDF náhled" style="max-height: 200px;" class="rounded shadow hover:shadow-lg transition-shadow" /></a>');
                                }

                                return new HtmlString('<span class="text-gray-400">Žádné PDF</span>');
                            })
                            ->columnSpan(2),
                        Placeholder::make('original_image_preview')
                            ->label('Původní obrázek')
                            ->content(function (?DeathNotice $record): HtmlString {
                                $media = $record?->getFirstMedia('original_image');
                                if ($media) {
                                    $url = $media->getUrl();

                                    return new HtmlString('<a href="'.e($url).'" target="_blank" class="block"><img src="'.e($url).'" alt="Původní obrázek" style="max-width: 300px; max-height: 200px;" class="rounded shadow hover:shadow-lg transition-shadow" /></a>');
                                }

                                return new HtmlString('<span class="text-gray-400">Žádný obrázek</span>');
                            })
                            ->columnSpan(2),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('pdf_thumbnail')
                    ->label('PDF')
                    ->getStateUsing(function (DeathNotice $record): ?string {
                        $media = $record->getFirstMedia('pdf');

                        return $media?->getUrl('thumb');
                    })
                    ->height(80)
                    ->width(57)
                    ->url(fn (DeathNotice $record): ?string => $record->getFirstMedia('pdf')?->getUrl())
                    ->openUrlInNewTab(),
                TextColumn::make('full_name')
                    ->label('Jméno')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('death_date')
                    ->label('Datum úmrtí')
                    ->date('j. n. Y')
                    ->sortable(),
                TextColumn::make('funeral_date')
                    ->label('Datum pohřbu')
                    ->date('j. n. Y')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Zdroj')
                    ->badge()
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderByRaw('COALESCE(death_date, DATE(created_at)) DESC')
                ->orderByRaw('CASE WHEN death_date IS NULL THEN 1 ELSE 0 END ASC'))
            ->filters([
                SelectFilter::make('source')
                    ->label('Zdroj')
                    ->options(fn (): array => DeathNotice::query()
                        ->distinct()
                        ->whereNotNull('source')
                        ->pluck('source', 'source')
                        ->toArray()),
                SelectFilter::make('death_date_period')
                    ->label('Datum úmrtí')
                    ->options([
                        'this_week' => 'Aktuální týden',
                        'last_week' => 'Minulý týden',
                        'this_month' => 'Aktuální měsíc',
                        'last_month' => 'Minulý měsíc',
                        'this_quarter' => 'Aktuální čtvrtletí',
                        'last_quarter' => 'Minulé čtvrtletí',
                        'this_year' => 'Aktuální rok',
                        'last_year' => 'Minulý rok',
                        'null' => 'Neuvedeno',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        return match ($value) {
                            'this_week' => $query->whereBetween('death_date', [now()->startOfWeek(), now()->endOfWeek()]),
                            'last_week' => $query->whereBetween('death_date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
                            'this_month' => $query->whereBetween('death_date', [now()->startOfMonth(), now()->endOfMonth()]),
                            'last_month' => $query->whereBetween('death_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
                            'this_quarter' => $query->whereBetween('death_date', [now()->startOfQuarter(), now()->endOfQuarter()]),
                            'last_quarter' => $query->whereBetween('death_date', [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()]),
                            'this_year' => $query->whereBetween('death_date', [now()->startOfYear(), now()->endOfYear()]),
                            'last_year' => $query->whereBetween('death_date', [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()]),
                            'null' => $query->whereNull('death_date'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('funeral_date_period')
                    ->label('Datum pohřbu')
                    ->options([
                        'this_week' => 'Aktuální týden',
                        'last_week' => 'Minulý týden',
                        'this_month' => 'Aktuální měsíc',
                        'last_month' => 'Minulý měsíc',
                        'this_quarter' => 'Aktuální čtvrtletí',
                        'last_quarter' => 'Minulé čtvrtletí',
                        'this_year' => 'Aktuální rok',
                        'last_year' => 'Minulý rok',
                        'null' => 'Neuvedeno',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        return match ($value) {
                            'this_week' => $query->whereBetween('funeral_date', [now()->startOfWeek(), now()->endOfWeek()]),
                            'last_week' => $query->whereBetween('funeral_date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]),
                            'this_month' => $query->whereBetween('funeral_date', [now()->startOfMonth(), now()->endOfMonth()]),
                            'last_month' => $query->whereBetween('funeral_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
                            'this_quarter' => $query->whereBetween('funeral_date', [now()->startOfQuarter(), now()->endOfQuarter()]),
                            'last_quarter' => $query->whereBetween('funeral_date', [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()]),
                            'this_year' => $query->whereBetween('funeral_date', [now()->startOfYear(), now()->endOfYear()]),
                            'last_year' => $query->whereBetween('funeral_date', [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()]),
                            'null' => $query->whereNull('funeral_date'),
                            default => $query,
                        };
                    }),
            ])
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('extract')
                    ->label('Extrahovat')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('warning')
                    ->form([
                        CheckboxList::make('fields')
                            ->label('Pole k extrakci')
                            ->options([
                                ExtractDeathDateAndAnnouncementJob::FIELD_ALL => 'Vše (kompletní re-extrakce)',
                                ExtractDeathDateAndAnnouncementJob::FIELD_FULL_NAME => 'Jméno',
                                ExtractDeathDateAndAnnouncementJob::FIELD_OPENING_QUOTE => 'Citát',
                                ExtractDeathDateAndAnnouncementJob::FIELD_DEATH_DATE => 'Datum úmrtí',
                                ExtractDeathDateAndAnnouncementJob::FIELD_ANNOUNCEMENT_TEXT => 'Text oznámení',
                            ])
                            ->default([ExtractDeathDateAndAnnouncementJob::FIELD_ALL])
                            ->required(),
                    ])
                    ->action(function (DeathNotice $record, array $data, VisionOcrService $visionOcrService): void {
                        $fields = $data['fields'];

                        // If 'all' is selected, use only 'all'
                        if (in_array(ExtractDeathDateAndAnnouncementJob::FIELD_ALL, $fields)) {
                            $fields = [ExtractDeathDateAndAnnouncementJob::FIELD_ALL];
                        }

                        try {
                            // Check if we have original_image (can run synchronously)
                            $imageMedia = $record->getFirstMedia('original_image');
                            if ($imageMedia && file_exists($imageMedia->getPath())) {
                                // Run extraction synchronously with original image
                                $imagePath = $imageMedia->getPath();
                                $ocrData = $visionOcrService->extractTextFromImage($imagePath, $record->full_name);

                                // Build update data
                                $updateData = [];
                                $shouldExtractField = fn (string $field): bool => in_array(ExtractDeathDateAndAnnouncementJob::FIELD_ALL, $fields)
                                    || in_array($field, $fields);

                                if ($shouldExtractField(ExtractDeathDateAndAnnouncementJob::FIELD_FULL_NAME)) {
                                    $updateData['full_name'] = $ocrData['full_name'] ?? $record->full_name;
                                }
                                if ($shouldExtractField(ExtractDeathDateAndAnnouncementJob::FIELD_OPENING_QUOTE)) {
                                    $updateData['opening_quote'] = $ocrData['opening_quote'] ?? null;
                                }
                                if ($shouldExtractField(ExtractDeathDateAndAnnouncementJob::FIELD_DEATH_DATE)) {
                                    $updateData['death_date'] = $ocrData['death_date'] ?? null;
                                }
                                if ($shouldExtractField(ExtractDeathDateAndAnnouncementJob::FIELD_ANNOUNCEMENT_TEXT)) {
                                    $updateData['announcement_text'] = $ocrData['announcement_text'] ?? null;
                                }

                                if (! empty($updateData)) {
                                    $record->update($updateData);
                                }

                                Notification::make()
                                    ->title('Extrakce dokončena')
                                    ->body('Extrahovaná pole: '.implode(', ', array_keys($updateData)))
                                    ->success()
                                    ->send();

                                return;
                            }

                            // No original image - check for PDF
                            $pdfMedia = $record->getFirstMedia('pdf');
                            if (! $pdfMedia) {
                                throw new \Exception('Parte nemá ani obrázek ani PDF.');
                            }

                            // PDF conversion needs terminal environment (Ghostscript in PATH)
                            // Dispatch to queue which runs in terminal
                            ExtractDeathDateAndAnnouncementJob::dispatch($record, null, $fields);

                            Notification::make()
                                ->title('Extrakce zařazena do fronty')
                                ->body('PDF bude zpracováno na pozadí. Ujistěte se, že běží Horizon nebo queue worker.')
                                ->info()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Chyba při extrakci')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    })
                    ->modalHeading('Extrahovat data z parte')
                    ->modalSubmitActionLabel('Extrahovat')
                    ->modalWidth('md'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeathNotices::route('/'),
            'edit' => Pages\EditDeathNotice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('media');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
