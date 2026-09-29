<?php

namespace App\Services;

use App\Enums\ContentNodeType;
use App\Models\Book;
use App\Models\ContentNode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BookContentService
{
    public function addChild(Book $book, array $data): ContentNode
    {
        $order = $data['order'] ?? (($book->nodes()->max('order') ?? 0) + 1);
        $content = $data['content'] ?? null;
        $contentHtml = $data['content_html'] ?? $content;

        return $book->nodes()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'type' => $data['type'] ?? ContentNodeType::CHAPTER->value,
            'title' => $data['title'],
            'slug' => $data['slug'] ?? (\App\Helpers\SlugHelper::generate($data['title']) ?: Str::uuid()->toString()),
            'order' => $order,
            'content_html' => $contentHtml,
            'plain_text' => $contentHtml ? strip_tags($contentHtml) : null,
            'content_json' => $data['content_blocks'] ?? [],
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Get the full hierarchy of a book (Titles only for sidebar).
     */
    public function getHierarchy(Book $book): Collection
    {
        return $book->nodes()
            ->orderBy('order')
            ->get(['id', 'parent_id', 'type', 'title', 'order']);
    }

    /**
     * Add a block to a specific child unit.
     */
    public function addBlock(ContentNode $child, array $block): ContentNode
    {
        $blocks = $child->content_json ?? [];

        if (isset($block['body'])) {
            $blocks[] = [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $block['body']
                    ]
                ]
            ];

            $currentContent = $child->content_html ?? '';
            $child->content_html = $currentContent . '<p>' . htmlspecialchars($block['body']) . '</p>';
            $child->plain_text = strip_tags($child->content_html);
        } else {
            $blocks[] = $block;
        }

        $child->content_json = $blocks;
        $child->save();

        return $child;
    }

    /**
     * Batch update order of content items.
     */
    public function updateOrder(Book $book, array $items): void
    {
        foreach ($items as $item) {
            $book->nodes()
                ->where('id', $item['id'])
                ->update([
                    'order' => $item['order'],
                    'parent_id' => $item['parent_id'] ?? null
                ]);
        }
    }
}
