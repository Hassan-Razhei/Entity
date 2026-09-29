<?php

namespace App\Models;

use App\Traits\HasCommonScopes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Traits\HasPolymorphicRelations;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property int $serial_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $file_path
 * @property int|null $duration
 * @property-read string $type
 * @property-read string $formatted_serial_number
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @mixin \Illuminate\Database\Query\Builder
 */
abstract class Entity extends Model
{
    use HasFactory, SoftDeletes, HasPolymorphicRelations, HasCommonScopes, HasUuids;


    /**
     * الخصائص المشتركة لجميع Entities
     */
    protected $fillable = ['title', 'slug'];

    protected $dates = ['deleted_at'];

    protected static function booted()
    {
        static::saving(function ($entity) {
            if (empty($entity->slug)) {
                $entity->slug = \App\Helpers\SlugHelper::generate($entity->title) ?: Str::uuid()->toString();
            }
        });

        static::deleting(function ($entity) {
            if (method_exists($entity, 'isForceDeleting') && $entity->isForceDeleting()) {
                $entity->nodes()->forceDelete();
            } else {
                $entity->nodes()->delete();
            }
        });
    }

    /**
     * العلاقة الموحدة مع عقد المحتوى في PostgreSQL
     */
    public function nodes()
    {
        return $this->morphMany(ContentNode::class, 'entity')->orderBy('order');
    }

    /**
     * الاسم المستعار للعلاقة لضمان التوافق الكامل مع الكود السابق
     */
    public function children()
    {
        return $this->nodes();
    }

    /**
     * Use slug instead of UUID for routing.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * الخصائص المشتركة
     */
    protected $appends = ['type', 'formatted_serial_number'];

    public function getTypeAttribute(): string
    {
        return class_basename($this);
    }

    public function getFormattedSerialNumberAttribute(): string
    {
        return '#' . str_pad($this->serial_number, 5, '0', STR_PAD_LEFT);
    }

    public static function getCached($id)
    {
        $cacheKey = "entity." . static::class . ".{$id}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($id) {
            return static::find($id);
        });
    }

    /**
     * جلب الـ Entity مع العلاقات من الكاش
     */
    public function getCachedWithRelations()
    {
        $cacheKey = "entity." . $this->getMorphClass() . ".{$this->getKey()}.with_relations";

        return Cache::remember($cacheKey, now()->addHours(1), function () {
            return $this->load(['tags', 'categories', 'activities', 'comments', 'reviews']);
        });
    }

    /**
     * الحصول على الإحصائيات من الكاش
     */
    public function getCachedStats()
    {
        $cacheKey = "entity." . $this->getMorphClass() . ".{$this->getKey()}.stats";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () {
            return [
                // 'views_count' => $this->views()->count(),
                // 'favourites_count' => $this->favourites()->count(),
                'comments_count' => $this->comments()->count(),
                'reviews_count' => $this->reviews()->count(),
                // 'average_rating' => $this->reviews()->avg('rating') ?? 0,
                'tags_count' => $this->tags()->count(),
                'categories_count' => $this->categories()->count(),
                'last_activity' => $this->activities()->latest()->first()?->created_at,
            ];
        });
    }
}
