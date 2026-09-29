<?php

namespace App\Models;

/**
 * Backwards compatibility adapter for AudioSegment -> ContentNode on PostgreSQL
 */
class AudioSegment extends ContentNode
{
    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->type)) {
                $model->type = 'segment';
            }
            if (empty($model->entity_type)) {
                $model->entity_type = 'audio';
            }
        });
    }

    public function getAudioIdAttribute(): ?string
    {
        return $this->entity_id;
    }

    public function setAudioIdAttribute($value): void
    {
        $this->attributes['entity_id'] = $value;
        $this->attributes['entity_type'] = 'audio';
    }

    public function audio()
    {
        return $this->belongsTo(Audio::class, 'entity_id');
    }
}
