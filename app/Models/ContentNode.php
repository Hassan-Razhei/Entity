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
        'content',
        'content_html',
        'plain_text',
        'content_json',
        'content_blocks',
        'json_content',
        'metadata',
        'versions',
        'is_manually_edited',
        'last_editor_id',
        'last_updated',
        'start_time',
        'end_time',
        'folio_number',
        'image_url',
        'description',
        'resource_url',
        'transcription_status',
        'page_number',
        'book_id',
        'manuscript_id',
        'audio_id',
        'video_id',
        'duration',
    ];

    protected $casts = [
        'order' => 'integer',
        'content_json' => 'array',
        'metadata' => 'array',
        'versions' => 'array',
    ];

    protected $appends = ['_id', 'start_time', 'end_time', 'image_url', 'folio_number', 'content', 'html_content'];

    protected static function booted()
    {
        static::creating(function ($node) {
            if (empty($node->type)) {
                $node->type = match ($node->entity_type) {
                    'book' => 'chapter',
                    'manuscript' => 'folio',
                    'audio', 'video' => 'segment',
                    default => 'paragraph',
                };
            }

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
            'content_blocks' => $this->content_blocks,
            'content_json' => $this->content_json,
            'content_html' => $this->content_html,
            'metadata' => $this->metadata,
            'created_at' => now()->toISOString(),
            'description' => $description,
        ];

        $this->versions = $versions;
        $this->save();
    }

    // ==================== Accessors & Mutators مساعدة للتوافق السلس ====================

    /**
     * هل تم التعديل يدوياً من المحرر
     */
    public function getIsManuallyEditedAttribute(): bool
    {
        return (bool) ($this->metadata['is_manually_edited'] ?? false);
    }

    public function setIsManuallyEditedAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['is_manually_edited'] = (bool) $value;
        $this->metadata = $meta;
    }

    /**
     * خاصية _id متوافقة مع الأنظمة السابقة
     */
    public function getIdAttribute(): ?string
    {
        return isset($this->attributes['id']) ? (string) $this->attributes['id'] : null;
    }

    public function get_IdAttribute(): ?string
    {
        return $this->id;
    }

    public function set_IdAttribute($value): void
    {
        $this->attributes['id'] = $value;
    }

    public function setAttribute($key, $value)
    {
        if ($key === '_id') {
            $key = 'id';
        }
        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        if ($key === '_id') {
            return (string) parent::getAttribute('id');
        }
        return parent::getAttribute($key);
    }

    /**
     * خاصية المحتوى (content) متوافقة مع الأنظمة السابقة
     */
    public function getContentAttribute(): ?string
    {
        return $this->content_html ?? $this->plain_text;
    }

    public function setContentAttribute(?string $value): void
    {
        $this->attributes['content_html'] = $value;
        $this->attributes['plain_text'] = $value ? strip_tags($value) : null;
    }

    public function getHtmlContentAttribute(): ?string
    {
        return $this->content_html;
    }

    public function setHtmlContentAttribute(?string $value): void
    {
        $this->setContentAttribute($value);
    }

    /**
     * خاصية مجموعات المحتوى المنظم (content_blocks) متوافقة مع الأنظمة السابقة
     */
    public function getContentBlocksAttribute(): array
    {
        return $this->content_json ?? [];
    }

    public function setContentBlocksAttribute(?array $value): void
    {
        $this->attributes['content_json'] = json_encode($value ?? []);
    }

    /**
     * خاصية json_content متوافقة مع محرر النصوص الموحد
     */
    public function getJsonContentAttribute(): ?array
    {
        return $this->content_json;
    }

    public function setJsonContentAttribute(?array $value): void
    {
        $this->content_json = $value;
    }

    /**
     * معرف آخر محرر
     */
    public function getLastEditorIdAttribute(): ?string
    {
        return $this->metadata['last_editor_id'] ?? null;
    }

    public function setLastEditorIdAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['last_editor_id'] = $value;
        $this->metadata = $meta;
    }

    /**
     * وقت آخر تعديل
     */
    public function getLastUpdatedAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->updated_at;
    }

    public function setLastUpdatedAttribute($value): void
    {
        // Automatically handled by Eloquent timestamps
    }

    /**
     * للصوتيات والمرئيات: بداية المقطع
     */
    public function getStartTimeAttribute(): ?float
    {
        return isset($this->metadata['start_time']) ? (float) $this->metadata['start_time'] : null;
    }

    public function setStartTimeAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['start_time'] = (float) $value;
        $this->metadata = $meta;
    }

    /**
     * للصوتيات والمرئيات: نهاية المقطع
     */
    public function getEndTimeAttribute(): ?float
    {
        return isset($this->metadata['end_time']) ? (float) $this->metadata['end_time'] : null;
    }

    public function setEndTimeAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['end_time'] = (float) $value;
        $this->metadata = $meta;
    }

    /**
     * مدة المقطع بالثواني
     */
    public function getDurationAttribute(): ?float
    {
        return isset($this->metadata['duration']) ? (float) $this->metadata['duration'] : null;
    }

    public function setDurationAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['duration'] = (float) $value;
        $this->metadata = $meta;
    }

    /**
     * للمخطوطات: رقم اللوحة
     */
    public function getFolioNumberAttribute(): ?string
    {
        return $this->metadata['folio_number'] ?? null;
    }

    public function setFolioNumberAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['folio_number'] = $value;
        $this->metadata = $meta;
    }

    /**
     * للمخطوطات: رابط صورة اللوحة
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->metadata['image_url'] ?? null;
    }

    public function setImageUrlAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['image_url'] = $value;
        $this->metadata = $meta;
    }

    /**
     * الوصف للمشاهد أو المقاطع
     */
    public function getDescriptionAttribute(): ?string
    {
        return $this->metadata['description'] ?? null;
    }

    public function setDescriptionAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['description'] = $value;
        $this->metadata = $meta;
    }

    /**
     * رابط المورد الخارجي
     */
    public function getResourceUrlAttribute(): ?string
    {
        return $this->metadata['resource_url'] ?? null;
    }

    public function setResourceUrlAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['resource_url'] = $value;
        $this->metadata = $meta;
    }

    /**
     * حالة النسخ/التفريغ
     */
    public function getTranscriptionStatusAttribute(): ?string
    {
        return $this->metadata['transcription_status'] ?? null;
    }

    public function setTranscriptionStatusAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['transcription_status'] = $value;
        $this->metadata = $meta;
    }

    /**
     * رقم الصفحة
     */
    public function getPageNumberAttribute(): ?int
    {
        return isset($this->metadata['page_number']) ? (int) $this->metadata['page_number'] : null;
    }

    public function setPageNumberAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['page_number'] = (int) $value;
        $this->metadata = $meta;
    }

    /**
     * محولات المفاتيح الأجنبية القديمة للتوافق الشامل
     */
    public function getBookIdAttribute(): ?string
    {
        return $this->entity_type === 'book' ? $this->entity_id : null;
    }

    public function setBookIdAttribute($value): void
    {
        $this->attributes['entity_id'] = $value;
        $this->attributes['entity_type'] = 'book';
    }

    public function getManuscriptIdAttribute(): ?string
    {
        return $this->entity_type === 'manuscript' ? $this->entity_id : null;
    }

    public function setManuscriptIdAttribute($value): void
    {
        $this->attributes['entity_id'] = $value;
        $this->attributes['entity_type'] = 'manuscript';
    }

    public function getAudioIdAttribute(): ?string
    {
        return $this->entity_type === 'audio' ? $this->entity_id : null;
    }

    public function setAudioIdAttribute($value): void
    {
        $this->attributes['entity_id'] = $value;
        $this->attributes['entity_type'] = 'audio';
    }

    public function getVideoIdAttribute(): ?string
    {
        return $this->entity_type === 'video' ? $this->entity_id : null;
    }

    public function setVideoIdAttribute($value): void
    {
        $this->attributes['entity_id'] = $value;
        $this->attributes['entity_type'] = 'video';
    }
}
