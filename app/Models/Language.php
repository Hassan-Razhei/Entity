<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Language extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'code',
    ];

    public function versions()
    {
        return $this->hasMany(Version::class);
    }

    public function books()
    {
        return $this->hasManyThrough(
            Book::class,
            Version::class,
            'language_id',
            'id',
            'id',
            'versionable_id'
        )->where('versions.versionable_type', 'book');
    }

    public function audios()
    {
        return $this->hasManyThrough(
            Audio::class,
            Version::class,
            'language_id',
            'id',
            'id',
            'versionable_id'
        )->where('versions.versionable_type', 'audio');
    }

    public function videos()
    {
        return $this->hasManyThrough(
            Video::class,
            Version::class,
            'language_id',
            'id',
            'id',
            'versionable_id'
        )->where('versions.versionable_type', 'video');
    }

    public function manuscripts()
    {
        return $this->hasManyThrough(
            Manuscript::class,
            Version::class,
            'language_id',
            'id',
            'id',
            'versionable_id'
        )->where('versions.versionable_type', 'manuscript');
    }
}
