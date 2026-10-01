<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManuscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'original_title' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:100',
            
            // Physical Metadata
            'catalog_number' => 'nullable|string|max:100',
            'scribe' => 'nullable|string|max:255',
            'copy_date' => 'nullable|string|max:100',
            'parts' => 'nullable|string|max:100',
            'script_type' => 'nullable|string|max:100',
            'dimensions' => 'nullable|string|max:100',
            'lines_per_page' => 'nullable|integer',
            'pages' => 'nullable|integer',
            'inscriptions' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_autograph' => 'boolean',
            'manuscript_start' => 'nullable|string',
            'manuscript_end' => 'nullable|string',
            
            'manuscript_century' => 'nullable|string|max:255',
            'manuscript_century_label' => 'nullable|string|max:255',

            'author_ids' => 'nullable|array',
            'author_ids.*' => 'exists:authors,id',
            'publisher_id' => 'nullable|exists:publishers,id',
            'published_year' => 'nullable|integer|min:1|max:' . (date('Y') + 1),
            'description' => 'nullable|string',
            'cover' => 'nullable|image|max:2048',
            'file' => 'nullable|mimes:pdf|max:51200', // 50MB
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'array',
            'tags.*' => 'exists:tags,id',
        ];
    }
}
