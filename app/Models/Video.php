<?php

namespace App\Models;


/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property int $duration
 * @property string $format
 * @property string|null $description
 * @property string|null $cover_path
 * @property string|null $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Video extends Entity
{
    protected $table = 'videos';

    protected $fillable = [
        'title',
        'code',
        'slug',
        'duration',
        'format',
        'description',
        'cover_path',
        'file_path',
        'created_at',
        'updated_at'
    ];

}
