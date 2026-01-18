<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DeathNotice extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'hash',
        'full_name',
        'opening_quote',
        'funeral_date',
        'death_date',
        'source',
        'source_url',
        'announcement_text',
        'has_photo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'funeral_date' => 'date',
            'death_date' => 'date',
            'has_photo' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')
            ->useDisk('parte')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);

        $this->addMediaCollection('portrait')
            ->useDisk('parte')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);
    }

    public function getFullNameAttribute(): ?string
    {
        return $this->attributes['full_name'] ?? null;
    }
}
