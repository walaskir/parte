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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'funeral_date' => 'date',
            'death_date' => 'date',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')
            ->useDisk('parte')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);

        $this->addMediaCollection('original_image')
            ->useDisk('parte')
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp']);
    }

    public function getFullNameAttribute(): ?string
    {
        return $this->attributes['full_name'] ?? null;
    }
}
