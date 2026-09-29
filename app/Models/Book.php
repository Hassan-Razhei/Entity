<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string $author
 * @property string|null $isbn
 * @property string|null $description
 * @property string|null $cover_path
 * @property string|null $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $display_name
 */
class Book extends Entity
{
    use HasFactory;
    protected $table = 'books';

    protected $fillable = [
        'title',
        'slug',
        'author',
        'isbn',
        'description',
        'cover_path',
        'file_path',
        'created_at',
        'updated_at'
    ];

    /**
     * الخصائص الخاصة بالكتاب
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->title} - {$this->author}";
    }

    /**
     * العلاقة مع المواضيع (Topics)
     */
    public function topics()
    {
        return $this->belongsToMany(Topic::class, 'book_topic');
    }
}
