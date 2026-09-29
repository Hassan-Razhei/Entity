<?php

namespace App\Models;

/**
 * Backwards compatibility adapter for BookChild -> ContentNode on PostgreSQL
 */
class BookChild extends ContentNode
{
    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {
            if (empty($model->type)) {
                $model->type = 'chapter';
            }
            if (empty($model->entity_type)) {
                $model->entity_type = 'book';
            }
        });
    }

    public function getBookIdAttribute(): ?string
    {
        return $this->entity_id;
    }

    public function setBookIdAttribute($value): void
    {
        $this->attributes['entity_id'] = $value;
        $this->attributes['entity_type'] = 'book';
    }

    public function book()
    {
        return $this->belongsTo(Book::class, 'entity_id');
    }
}
