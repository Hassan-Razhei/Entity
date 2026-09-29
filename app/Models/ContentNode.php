<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $entity_type
 * @property string $entity_id
 * @property string|null $parent_id
 * @property string $type
 * @property string $title
 * @property string $slug
 * @property int $order
 * @property string|null $content_html
 * @property string|null $plain_text
 * @property array|null $content_json
 * @property array|null $metadata
 * @property array|null $versions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Model|\Eloquent $entity
 * @property-read ContentNode|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|ContentNode[] $children
 */
class ContentNode extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'content_nodes';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'parent_id',
        'type',
        'title',
        'slug',
        'order',
        'content_html',
        'plain_text',
        'content_json',
        'metadata',
        'versions',
    ];

    protected $casts = [
        'order' => 'integer',
        'content_json' => 'array',
        'metadata' => 'array',
        'versions' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($node) {
            if (empty($node->slug)) {
                $node->slug = \App\Helpers\SlugHelper::generate($node->title) ?: Str::uuid()->toString();
            }
        });
    }

    /**
     * العلاقة مع الكيان الأصلي (كتاب، مخطوطة، صوت، فيديو)
     */
    public function entity()
    {
        return $this->morphTo();
    }

    /**
     * العقدة الأب
     */
    public function parent()
    {
        return $this->belongsTo(ContentNode::class, 'parent_id');
    }

    /**
     * العقد الفرعية مرتبة
     */
    public function children()
    {
        return $this->hasMany(ContentNode::class, 'parent_id')->orderBy('order');
    }

    /**
     * الشجرة الهرمية العميقة المتتالية
     */
    public function recursiveChildren()
    {
        return $this->children()->with('recursiveChildren');
    }

    /**
     * إنشاء وحفظ نسخة تاريخية في حقل versions (بديل لما كان في MongoDB)
     */
    public function createVersion(string $description = 'Manual Edit'): void
    {
        $versions = $this->versions ?? [];
        $versions[] = [
            'content_json' => $this->content_json,
            'content_html' => $this->content_html,
            'metadata' => $this->metadata,
            'created_at' => now()->toISOString(),
            'description' => $description,
        ];

        $this->versions = $versions;
        $this->save();
    }

    // ==================== Accessors مساعدة للتوافق السلس ====================

    /**
     * للصوتيات والمرئيات: بداية المقطع
     */
    public function getStartTimeAttribute(): ?float
    {
        return isset($this->metadata['start_time']) ? (float) $this->metadata['start_time'] : null;
    }

    /**
     * للصوتيات والمرئيات: نهاية المقطع
     */
    public function getEndTimeAttribute(): ?float
    {
        return isset($this->metadata['end_time']) ? (float) $this->metadata['end_time'] : null;
    }

    /**
     * للمخطوطات: رقم اللوحة
     */
    public function getFolioNumberAttribute(): ?string
    {
        return $this->metadata['folio_number'] ?? null;
    }

    /**
     * للمخطوطات: رابط صورة اللوحة
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->metadata['image_url'] ?? null;
    }
}
