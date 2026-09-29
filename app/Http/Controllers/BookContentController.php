<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ContentNode;
use App\Services\BookContentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class BookContentController extends Controller
{
    protected $contentService;

    public function __construct(BookContentService $contentService)
    {
        $this->contentService = $contentService;
    }

    /**
     * عارض الكتاب الرئيسي
     */
    public function show(Book $book, $childId = null): Response
    {
        $initialContent = null;
        if ($childId) {
            $child = $book->nodes()->where('id', $childId)->first();
            if ($child) {
                $initialContent = [
                    'id' => $child->id,
                    'title' => $child->title,
                    'type' => $child->type,
                    'content_blocks' => $child->content_blocks ?? [],
                    'metadata' => $child->metadata ?? []
                ];
            }
        }

        return Inertia::render('Books/Reader/Index', [
            'book' => $book->only(['id', 'title', 'slug', 'author']),
            'initialHierarchy' => $this->contentService->getHierarchy($book)->map(fn($item) => [
                'id' => (string) $item->id,
                'parent_id' => $item->parent_id ? (string) $item->parent_id : null,
                'type' => $item->type,
                'title' => $item->title,
                'order' => $item->order
            ]),
            'initialContent' => $initialContent,
            'childId' => $childId
        ]);
    }

    /**
     * جلب محتويات وحدة معينة (فصل، مسألة، إلخ)
     */
    public function getChildContent(ContentNode $child): JsonResponse
    {
        return response()->json([
            'content_blocks' => $child->content_blocks ?? [],
            'title' => $child->title,
            'type' => $child->type,
            'metadata' => $child->metadata ?? []
        ]);
    }

    public function updateValidation(Request $request, $id): JsonResponse
    {
        $child = ContentNode::find($id);
        if (!$child) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validated = $request->validate([
            'content_blocks' => 'array',
        ]);

        // Create version of current state before updating
        $child->createVersion('Manual update from editor');

        $child->content_blocks = $validated['content_blocks'];
        $child->is_manually_edited = true;
        $child->save();

        return response()->json(['message' => 'Saved']);
    }

    public function restoreVersion(Request $request, $id, $version = null): JsonResponse
    {
        $child = ContentNode::find($id);
        if (!$child) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $versionIndex = $version ?? $request->input('version', 0);
        $versions = $child->versions ?? [];

        if (isset($versions[$versionIndex])) {
            $child->content_blocks = $versions[$versionIndex]['content_blocks'] ?? [];
            $child->save();
            return response()->json(['message' => 'Restored']);
        }

        return response()->json(['message' => 'Version not found'], 422);
    }
}
