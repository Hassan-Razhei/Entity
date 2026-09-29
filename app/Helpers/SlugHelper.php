<?php

namespace App\Helpers;

class SlugHelper
{
    public static function arabicSlug(string $text): string
    {
        // 1. Convert to lowercase for Latin characters
        $slug = mb_strtolower($text, 'UTF-8');
        
        // 2. Replace multiple spaces/underscores with a single dash
        $slug = preg_replace('/[\s_]+/u', '-', $slug);
        
        // 3. Remove any character that is NOT a letter (Arabic/Latin/etc), a number, or a dash
        $slug = preg_replace('/[^\p{L}\p{N}\-]/u', '', $slug);
        
        // 4. Trim dashes from ends
        return trim($slug, '-');
    }
    public static function generate(string $text): string
    {
        return static::arabicSlug($text);
    }

    /**
     * Generate a unique slug for a model
     * @param string $model Fully qualified model class
     * @param string $text The text to slugify
     * @param string $column The database column to check
     * @return string
     */
    public static function uniqueSlug(string $model, string $text, string $column = 'slug'): string
    {
        $baseSlug = static::generate($text);
        
        // If empty (e.g. only special characters), fallback
        if (empty($baseSlug)) {
            $baseSlug = 'item';
        }

        $slug = $baseSlug;
        
        // Helper to check existence (including trashed)
        $existsQuery = function($s) use ($model, $column) {
            $query = $model::where($column, $s);
            $traits = class_uses_recursive($model);
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', $traits)) {
                $query->withTrashed();
            }
            return $query->exists();
        };

        // Check if slug exists
        if (!$existsQuery($slug)) {
            return $slug;
        }

        // Determine padding/format
        // User wants "codes" (e.g. -01) only for Manuscripts
        $isManuscript = (str_contains($model, 'Manuscript'));
        
        $i = 1;
        while ($existsQuery($slug)) {
            if ($isManuscript) {
                $slug = $baseSlug . '-' . str_pad($i++, 2, '0', STR_PAD_LEFT);
            } else {
                $slug = $baseSlug . '-' . $i++;
            }
            
            // Safety break 
            if ($i > 99) {
                $slug = $baseSlug . '-' . \Illuminate\Support\Str::random(6);
                break;
            }
        }

        return $slug;
    }

    /**
     * Generate a unique sequential code for a model
     * @param string $model Fully qualified model class
     * @param string $title The title to abbreviate
     * @param string $column The database column to check
     * @param int $padding Number of zeros to pad
     * @return string
     */
    public static function uniqueCode(string $model, string $title, string $column = 'code', int $padding = 4): string
    {
        $prefix = static::getInitials($title);
        
        // Find the latest record to get the next sequential number
        // We look for any code ending in a number to keep the sequence global for the model
        $operator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? '~' : 'REGEXP';
        $latest = $model::where($column, $operator, '-[0-9]+$')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latest) {
            $nextNumber = 1;
        } else {
            // Extract the last number from the code (e.g., ص-ب-0023 -> 23)
            if (preg_match('/-(\d+)$/', $latest->{$column}, $matches)) {
                $nextNumber = (int)$matches[1] + 1;
            } else {
                $nextNumber = 1;
            }
        }

        $code = $prefix . '-' . str_pad($nextNumber, $padding, '0', STR_PAD_LEFT);

        // Double check uniqueness (just in case)
        while ($model::where($column, $code)->exists()) {
            $nextNumber++;
            $code = $prefix . '-' . str_pad($nextNumber, $padding, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    /**
     * Get initials from text, joined by a separator
     */
    public static function getInitials(string $text, string $separator = '-'): string
    {
        // Remove special characters and split by spaces
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);
        
        $initials = [];
        foreach ($words as $word) {
            // Remove 'ال' (Alif-Lam) if word starts with it (Arabic definite article)
            if (mb_strpos($word, 'ال') === 0 && mb_strlen($word, 'UTF-8') > 2) {
                $word = mb_substr($word, 2, null, 'UTF-8');
            }
            $initials[] = mb_substr($word, 0, 1, 'UTF-8');
        }

        return implode($separator, $initials);
    }
}
