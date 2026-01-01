<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuneralService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'homepage_url',
        'parte_url',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function deathNotices(): HasMany
    {
        return $this->hasMany(DeathNotice::class, 'source', 'name');
    }
}
