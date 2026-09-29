<?php

namespace App\Traits;

use App\Models\Activity;
use App\Models\Audio;
use App\Models\Book;
use App\Models\Comment;
use App\Models\Manuscript;
use App\Models\Review;
use App\Models\Tag;
use App\Models\Category;
use App\Models\Note;
use App\Models\Deletion;
use App\Models\Collection;
use App\Models\Series;
use App\Models\Author;
use App\Models\Booker;
use App\Models\Version;
use App\Models\Video;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

trait HasPolymorphicRelations
{
    /**
     * العلاقة مع النسخ (Versions - Polymorphic)
     */
    public function versions()
    {
        return $this->morphMany(Version::class, 'versionable');
    }

    /**
     * العلاقة مع المؤلفين (Authors - Polymorphic)
     */
    public function authors()
    {
        return $this->morphToMany(Author::class, 'authorable');
    }

    /**
     * العلاقة مع المساهمين (Bookers - Polymorphic)
     */
    public function bookers()
    {
        return $this->morphToMany(Booker::class, 'bookable');
    }

    /**
     * علاقة One-to-Many بوليمورفية
     */
    public function activities()
    {
        return $this->morphMany(Activity::class, 'entity');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'entity');
    }

    /*
    public function reviews()
    {
        return $this->morphMany(Review::class, 'entity');
    }
    */

    /*
    public function favourites()
    {
        return $this->morphMany(Favourite::class, 'entity');
    }
    */

    public function notes()
    {
        return $this->morphMany(Note::class, 'entity');
    }

    public function deletions()
    {
        return $this->morphMany(Deletion::class, 'entity');
    }

    /*
    public function searchTerms()
    {
        return $this->morphMany(SearchTerm::class, 'entity');
    }
    */

    /*
    public function views()
    {
        return $this->morphMany(View::class, 'entity');
    }
    */

    /**
     * علاقة Many-to-Many بوليمورفية (الأصلية)
     */
    public function tags()
    {
        $pivotTable = property_exists($this, 'tagsPivotTable')
            ? $this->tagsPivotTable
            : 'taggables';

        return $this->morphToMany(Tag::class, 'entity', $pivotTable)
            ->withTimestamps();
    }

    public function categories()
    {
        $pivotTable = property_exists($this, 'categoriesPivotTable')
            ? $this->categoriesPivotTable
            : 'categorizables';

        return $this->morphToMany(Category::class, 'entity', $pivotTable)
            ->withTimestamps();
    }

    public function collections()
    {
        return $this->morphToMany(Collection::class, 'entity', 'collectables')
            ->withPivot(['order_column', 'added_at']);
    }

    public function series()
    {
        return $this->morphToMany(Series::class, 'entity', 'seriables')
            ->withPivot('position');
    }

    // ==================== دوال مساعدة للـ Many-to-Many ====================

    /**
     * أنواع الـ Entities المدعومة
     */
    protected function getSupportedEntityTypes(): array
    {
        return [
            'Book' => Book::class,
            'Video' => Video::class,
            'Audio' => Audio::class,
            'Manuscript' => Manuscript::class,
        ];
    }

    /**
     * Accessor أساسي لجمع الـ Entities (لـ Tag, Category, Collection, Series)
     */
    protected function getPolymorphicEntities(string $relationName, string $pivotTable = null): EloquentCollection
    {
        $results = new EloquentCollection();

        foreach ($this->getSupportedEntityTypes() as $typeClass) {
            if (class_exists($typeClass)) {
                $entities = $typeClass::whereHas($relationName, function ($query) {
                    $query->where($this->getTable() . '.id', $this->id);
                })->get();

                // إضافة pivot data
                if ($pivotTable) {
                    $entities->each(function ($entity) use ($relationName) {
                        $pivot = $entity->{$relationName}()
                            ->where($this->getTable() . '.id', $this->id)
                            ->first()
                            ->pivot ?? null;

                        if ($pivot) {
                            $entity->pivot_data = $pivot->getAttributes();
                        }
                    });
                }

                $results = $results->concat($entities);
            }
        }

        return $results;
    }

    /**
     * Accessor للـ Entities مرتبطة بـ Tags
     */
    protected function getEntitiesFromTags(): EloquentCollection
    {
        return $this->getPolymorphicEntities('tags', 'taggables');
    }

    /**
     * Accessor للـ Entities مرتبطة بـ Collections
     */
    protected function getEntitiesFromCollections(): EloquentCollection
    {
        $entities = $this->getPolymorphicEntities('collections', 'collectables');

        return $entities->sortBy(function ($entity) {
            return $entity->pivot_data['order_column'] ?? 0;
        })->values();
    }

    /**
     * Accessor للـ Entities مرتبطة بـ Series
     */
    protected function getEntitiesFromSeries(): EloquentCollection
    {
        $entities = $this->getPolymorphicEntities('series', 'seriables');

        return $entities->sortBy(function ($entity) {
            return $entity->pivot_data['position'] ?? 0;
        })->values();
    }

    /**
     * إضافة entity إلى العلاقة
     */
    public function attachToEntity($entity, string $relationType, array $pivotData = []): bool
    {
        $method = $relationType; // tags, collections, series

        if (method_exists($entity, $method)) {
            $entity->{$method}()->attach($this->id, $pivotData);
            return true;
        }

        return false;
    }

    /**
     * فصل entity من العلاقة
     */
    public function detachFromEntity($entity, string $relationType): bool
    {
        $method = $relationType;

        if (method_exists($entity, $method)) {
            $entity->{$method}()->detach($this->id);
            return true;
        }

        return false;
    }

    /**
     * هل الـ Model مرتبط بـ entity معين
     */
    public function isAttachedTo($entity, string $relationType): bool
    {
        $method = $relationType;

        if (method_exists($entity, $method)) {
            return $entity->{$method}()
                ->where($this->getTable() . '.id', $this->id)
                ->exists();
        }

        return false;
    }

    /**
     * الحصول على pivot data لـ entity معين
     */
    public function getPivotDataForEntity($entity, string $relationType): ?array
    {
        $method = $relationType;

        if (method_exists($entity, $method)) {
            $pivot = $entity->{$method}()
                ->where($this->getTable() . '.id', $this->id)
                ->first()
                ->pivot ?? null;

            return $pivot ? $pivot->getAttributes() : null;
        }

        return null;
    }

    /**
     * العلاقة الموحدة مع عقد المحتوى في PostgreSQL (Unified Content)
     */
    public function contents()
    {
        return $this->nodes();
    }
}
