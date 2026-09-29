<?php

namespace App\Models;

/**
 * Backwards compatibility adapter for VideoSegment -> ContentNode on PostgreSQL
 */
class VideoSegment extends ContentNode
{
    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->type)) {
                $model->type = 'segment';
            }
            if (empty($model->entity_type)) {
                $model->entity_type = 'video';
            }
        });
    }

    public function getVideoIdAttribute(): ?string
    {
        return $this->entity_id;
    }

    public function setVideoIdAttribute($value): void
    {
        $this->attributes['entity_id'] = $value;
        $this->attributes['entity_type'] = 'video';
    }

    public function video()
    {
        return $this->belongsTo(Video::class, 'entity_id');
    }
}
