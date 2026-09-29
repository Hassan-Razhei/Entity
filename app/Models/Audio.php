<?php

namespace App\Models;


/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property int $duration
 * @property string $format
 * @property int|null $bitrate
 * @property int|null $sample_rate
 * @property int|null $file_size
 * @property string|null $description
 * @property string|null $cover_path
 * @property string|null $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read float $duration_in_minutes
 * @property-read string $duration_formatted
 * @property-read string $bitrate_formatted
 * @property-read string $sample_rate_formatted
 */
class Audio extends Entity
{
    protected $table = 'audios';

    protected $fillable = [
        'title',
        'code',
        'slug',
        'duration',
        'format',
        'bitrate',
        'sample_rate',
        'file_size',
        'description',
        'cover_path',
        'file_path',
        'created_at',
        'updated_at'
    ];



    /**
     * خصائص إضافية للصوت
     */

    public function getDurationInMinutesAttribute(): float
    {
        return $this->duration / 60;
    }

    public function getDurationFormattedAttribute(): string
    {
        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getBitrateFormattedAttribute(): string
    {
        return $this->bitrate . ' kbps';
    }

    public function getSampleRateFormattedAttribute(): string
    {
        return $this->sample_rate . ' Hz';
    }

}
