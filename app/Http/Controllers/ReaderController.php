<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Models\Book;
use App\Models\Audio;
use App\Models\Video;
use App\Models\Manuscript;
use App\Models\Entity;
use App\Models\ContentNode;
use App\Services\EntityContentService;
use App\Services\ReadingPositionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReaderController extends Controller
{
    protected $contentService;
    protected $positionService;

    public function __construct(EntityContentService $contentService, ReadingPositionService $positionService)
    {
        $this->contentService = $contentService;
        $this->positionService = $positionService;
    }

    /**
     * Display the Reader for a specific entity/content node.
     * Path: /reader/{type}/{slug}/{childId?}
     */
    public function show(string $type, string $slug, ?string $childId = null)
    {
        // 1. Resolve Parent Entity
        $parentEntity = $this->resolveParentEntity($type, $slug);

        if (!$parentEntity) {
            $childNode = $this->resolveEntityNode($type, $slug);
            if ($childNode && $childNode->entity) {
                 return redirect()->route('reader.show', ['type' => $type, 'slug' => $childNode->entity->slug, 'childId' => $childNode->id]);
            }
            abort(404, 'المصدر غير موجود');
        }

        $entity = $parentEntity;
        $entity->load(['authors', 'categories', 'tags', 'children']);

        // 2. Resolve Content
        $node = null;
        $htmlContent = '';
        $jsonContent = null;
        $isFullView = false;
        $currentNodeSlug = null;

        if ($childId) {
            if (Str::isUuid($childId)) {
                $node = $entity->nodes()->where('id', $childId)->first();
            }
            if (!$node) {
                $node = $entity->nodes()->where('slug', $childId)->first();
            }

            if (!$node) {
                abort(404, 'المقطع المحدد غير موجود');
            }

            $htmlContent = $node->content_html ?? $node->plain_text ?? '';
            $jsonContent = $node->content_json ?? ['type' => 'doc', 'content' => []];
            $currentNodeSlug = $node->slug;
        } else {
            // FULL VIEW
            $htmlContent = $this->contentService->aggregateFullContent($entity);
            $isFullView = true;
            $node = $this->contentService->getFirstChild($entity);
        }

        // 3. Prepare Content Data
        $data = $this->contentService->prepareEditorData($entity, $currentNodeSlug);
        
        // 4. Get Reading Position for the User
        $savedPosition = null;
        if (auth()->check()) {
            $savedPosition = $this->positionService->getPosition(auth()->user(), $entity);
        }

        // 5. Special Handling for Manuscripts (Vertical Scroll)
        $siblingsContent = [];
        if (EntityType::tryFrom($type) === EntityType::MANUSCRIPT) {
            $siblingsContent = $entity->children->map(function($child) {
                return [
                    'id' => $child->id,
                    'slug' => $child->slug,
                    'title' => $child->title,
                    'content' => $child->content_json ?? ['type' => 'doc', 'content' => []],
                    'html_content' => $child->content_html ?? $child->plain_text ?? '',
                    'metadata' => $child->metadata ?? [],
                ];
            });
        }

        return Inertia::render('Technologies/Reader/ReaderClient', [
            'type' => $type,
            'entity' => $entity,
            'content' => $jsonContent,
            'html_content' => $htmlContent,
            'isFullView' => $isFullView,
            'activeChildId' => $isFullView ? null : $node?->id,
            'activeSlug' => $currentNodeSlug,
            'hierarchy' => $entity->children, 
            'readingPosition' => $savedPosition,
            'title' => $entity->title . ' | القارئ',
            'siblings_content' => $siblingsContent, 
        ]);
    }

    /**
     * Save reading position (API endpoint)
     */
    public function savePosition(Request $request)
    {
        $request->validate([
            'entity_id' => 'required',
            'entity_type' => 'required',
            'node_slug' => 'required|string',
            'scroll_offset' => 'nullable|integer',
            'timestamp' => 'nullable|integer',
        ]);

        $user = auth()->user();
        $entityClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($request->entity_type) ?? $request->entity_type;
        $entity = $entityClass::findOrFail($request->entity_id);

        $this->positionService->savePosition($user, $entity, $request->only(['node_slug', 'scroll_offset', 'timestamp']));

        return response()->json(['message' => 'تم حفظ موضع القراءة']);
    }

    /**
     * Search across all content nodes for a specific entity.
     */
    public function search(Request $request, string $type, string $slug)
    {
        $query = $request->input('q');
        if (empty($query)) {
            return response()->json(['results' => []]);
        }

        $entityType = EntityType::tryFrom($type);
        if (!$entityType) abort(404, "Unknown entity type");

        $entity = $this->resolveParentEntity($type, $slug);
        
        if (!$entity) {
             $entity = $this->resolveEntity($type, $slug);
        }

        // Query Content Nodes on PostgreSQL
        $results = $entity->nodes()
            ->where(function($q) use ($query) {
                $q->where('plain_text', 'ILIKE', "%{$query}%")
                  ->orWhere('title', 'ILIKE', "%{$query}%");
            })
            ->orderBy('order', 'asc')
            ->get(); 

        $formattedResults = $results->map(function($node) use ($query) {
            $snippet = '';
            $plainText = $node->plain_text ?? '';
            
            if ($plainText) {
                $pos = mb_stripos($plainText, $query);
                if ($pos !== false) {
                    $start = max(0, $pos - 40);
                    $length = mb_strlen($query) + 80;
                    $snippet = mb_substr($plainText, $start, $length);
                    if ($start > 0) $snippet = '...' . $snippet;
                    if (mb_strlen($plainText) > $start + $length) $snippet .= '...';
                } else {
                    $snippet = mb_substr($plainText, 0, 100) . '...';
                }
            }

            return [
                'id' => $node->id,
                'slug' => $node->slug,
                'title' => $node->title,
                'snippet' => $snippet,
                'timestamp' => $node->start_time ?? null,
                'page' => $node->metadata['page_number'] ?? null,
            ];
        });

        return response()->json([
            'results' => $formattedResults,
            'count' => $formattedResults->count(),
            'query' => $query
        ]);
    }

    protected function resolveEntity(string $type, string $slug): Entity
    {
        $entityType = EntityType::tryFrom($type);
        if (!$entityType) abort(404, "Unknown entity type");

        $entityModel = $entityType->modelClass();
        $node = ContentNode::where('slug', $slug)->firstOrFail();

        $entity = $entityModel::findOrFail($node->entity_id);
        $entity->load('children');

        return $entity;
    }

    protected function resolveEntityNode(string $type, string $slug): ?ContentNode
    {
        return ContentNode::where('slug', $slug)->first();
    }

    protected function resolveParentEntity(string $type, string $slug): ?Entity
    {
        $entityType = EntityType::tryFrom($type);
        if (!$entityType) return null;

        $modelClass = $entityType->modelClass();
        return $modelClass::where('slug', $slug)->first();
    }
}
