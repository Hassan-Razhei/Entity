<?php

namespace App\Repositories;

use App\Models\Entity;
use App\Models\Book;
use App\Models\Video;
use App\Models\Audio;
use App\Models\Manuscript;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EntityBaseRepository
{
    /**
     * العثور على entity بواسطة ID
     */
    public function find(string $id): ?Entity
    {
        if (!\Illuminate\Support\Str::isUuid($id)) {
            return null;
        }

        // البحث في جميع أنواع الـ Entities
        $types = $this->getEntityClasses();

        foreach ($types as $type) {
            $entity = $type::find($id);
            if ($entity) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * العثور على entity بواسطة slug
     */
    public function findBySlug(string $slug): ?Entity
    {
        $types = $this->getEntityClasses();

        foreach ($types as $type) {
            $entity = $type::where('slug', $slug)->first();
            if ($entity) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * العثور على entity بواسطة النوع والـ ID
     */
    public function findByType(string $type, string $id): ?Entity
    {
        if (!\Illuminate\Support\Str::isUuid($id)) {
            return null;
        }

        $entityClass = $this->resolveEntityClass($type);

        return $entityClass::find($id);
    }

    /**
     * العثور على entity مع العلاقات
     */
    public function findWithRelations(string $id, array $relations = []): ?Entity
    {
        if (!\Illuminate\Support\Str::isUuid($id)) {
            return null;
        }

        $types = $this->getEntityClasses();

        foreach ($types as $type) {
            $entity = $type::with($relations)->find($id);
            if ($entity) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * الحصول على جميع الـ Entities
     */
    public function all(array $filters = []): Collection
    {
        $results = new Collection();
        $entityClasses = $this->getEntityClasses();

        // فلترة حسب النوع إذا كان محدداً
        if (isset($filters['type'])) {
            $entityClasses = [$this->resolveEntityClass($filters['type'])];
            unset($filters['type']);
        }

        foreach ($entityClasses as $entityClass) {
            $query = $entityClass::query();

            // تطبيق الفلاتر
            foreach ($filters as $key => $value) {
                if (in_array($key, (new $entityClass())->getFillable())) {
                    $query->where($key, $value);
                }
            }

            $results = $results->merge($query->get());
        }

        return $results;
    }

    /**
     * Paginate الـ Entities
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $allEntities = $this->all();

        $page = request()->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginated = $allEntities->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $paginated,
            $allEntities->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * إنشاء entity جديد
     */
    public function create(array $data): Entity
    {
        $entityClass = $this->resolveEntityClass($data['type']);

        return $entityClass::create($data);
    }

    /**
     * تحديث entity
     */
    public function update(Entity $entity, array $data): bool
    {
        return $entity->update($data);
    }

    /**
     * حذف entity (soft delete)
     */
    public function delete(Entity $entity): bool
    {
        return $entity->delete();
    }

    /**
     * استعادة entity محذوف
     */
    public function restore(Entity $entity): bool
    {
        return $entity->restore();
    }

    /**
     * الحصول على جميع أنواع الـ Entities
     */
    private function getEntityClasses(): array
    {
        return [
            Book::class,
            Video::class,
            Audio::class,
            Manuscript::class,
        ];
    }

    /**
     * تحويل type إلى entity class
     */
    private function resolveEntityClass(string $type): string
    {
        return match($type) {
            'book' => Book::class,
            Book::class => Book::class,
            'video' => Video::class,
            Video::class => Video::class,
            'audio' => Audio::class,
            Audio::class => Audio::class,
            'manuscript' => Manuscript::class,
            Manuscript::class => Manuscript::class,
            default => throw new \InvalidArgumentException("نوع غير معروف: {$type}")
        };
    }
}
