<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DeathNotice extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'hash',
        'full_name',
        'funeral_date',
        'death_date',
        'source',
        'source_url',
    ];

    protected $casts = [
        'funeral_date' => 'date',
        'death_date' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')
            ->useDisk('parte')
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function getFullNameAttribute(): string
    {
        return $this->attributes['full_name'];
    }
}
