<?php

namespace App\Models;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string|null $author
 * @property int|null $century
 * @property string|null $language
 * @property int|null $pages
 * @property string|null $publisher
 * @property string|null $location
 * @property string|null $description
 * @property string|null $cover_path
 * @property string|null $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $century_display
 * @property-read int $age
 * @property-read string $pages_formatted
 */
class Manuscript extends Entity
{
    protected $table = 'manuscripts';

    protected $fillable = [
        'title',
        'original_title',
        'code',
        'slug',
        'catalog_number',
        'scribe',
        'copy_date',
        'parts',
        'script_type',
        'dimensions',
        'lines_per_page',
        'inscriptions',
        'notes',
        // 'author', // Removed: migrated to authorables polymorphic relation
        'manuscript_century',
        'manuscript_century_label',
        'manuscript_start',
        'manuscript_end',
        'is_autograph',
        'pages',
        'location',
        'description',
        'cover_path',
        'file_path',
        'created_at',
        'updated_at'
    ];



    /**
     * خصائص إضافية للمخطوطة
     */

    public function getCenturyDisplayAttribute(): string
    {
        $century = (int) $this->manuscript_century;

        if ($century <= 0) {
            return "قبل هجرة";
        }

        $hijriCentury = $century - 600; // تقدير تقريبي
        return "القرن {$century} الميلادي (القرن {$hijriCentury} الهجري تقريباً)";
    }

    public function getAgeAttribute(): int
    {
        $currentYear = (int) date('Y');
        $centuryStart = (((int) $this->manuscript_century) - 1) * 100 + 1;
        return $currentYear - $centuryStart;
    }

    public function getPagesFormattedAttribute(): string
    {
        return "{$this->pages} صفحة";
    }

    public function isAncient(): bool
    {
        return ((int) $this->manuscript_century) < 15; // قبل القرن 15
    }

    public function isModern(): bool
    {
        return ((int) $this->manuscript_century) >= 19; // بعد القرن 19
    }


    /**
     * العلاقة مع نسخ المخطوطة (Versions)
     */
    public function versions()
    {
        return $this->hasMany(Version::class, 'versionable_id', 'id')
            ->where('versionable_type', self::class);
    }
}
