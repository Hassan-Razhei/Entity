<?php

namespace App\Models;

/**
 * Backwards compatibility adapter for ManuscriptChild -> ContentNode on PostgreSQL
 */
class ManuscriptChild extends ContentNode
{
    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->type)) {
                $model->type = 'folio';
            }
            if (empty($model->entity_type)) {
                $model->entity_type = 'manuscript';
            }
        });
    }

    public function getManuscriptIdAttribute(): ?string
    {
        return $this->entity_id;
    }

    public function setManuscriptIdAttribute($value): void
    {
        $this->attributes['entity_id'] = $value;
        $this->attributes['entity_type'] = 'manuscript';
    }

    public function manuscript()
    {
        return $this->belongsTo(Manuscript::class, 'entity_id');
    }
}
