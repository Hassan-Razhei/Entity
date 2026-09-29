<?php

namespace App\Observers;

use App\Models\Entity;

class EntityContentObserver
{
    /**
     * عند حذف أي كيان (كتاب، مخطوطة، صوت، فيديو)، تُحذف كل عقده تلقائياً
     */
    public function deleted(Entity $entity): void
    {
        $entity->nodes()->delete();
    }

    public function forceDeleted(Entity $entity): void
    {
        $entity->nodes()->forceDelete();
    }
}
