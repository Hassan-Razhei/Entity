<?php

namespace App\Http\Controllers;

use App\Enums\EntityType;
use App\Enums\ContentNodeType;
use App\Models\Book;
use App\Models\Audio;
use App\Models\Video;
use App\Models\Manuscript;
use App\Models\Entity;
use App\Models\ContentNode;
use App\Services\EntityContentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UnifiedEditorController extends Controller
{
    protected $contentService;

    public function __construct(EntityContentService $contentService)
    {
        $this->contentService = $contentService;
    }

    /**
     * المسار الموحد للمحرر: /studio/{type}/{slug}/{childId?}
     * slug is PARENT slug. childId is specific node ID.
     */
    public function show(string $type, string $slug, ?string $childId = null)
    {
        $entityType = EntityType::tryFrom($type);
        if (!$entityType) {
            abort(404, 'Invalid entity type');
        }

        // 1. Resolve Parent Entity
        $parentEntity = $this->resolveParentEntity($entityType, $slug);

        if (!$parentEntity) {
            abort(404, 'Parent resource not found');
        }

        // 2. Load Content
        // Always aggregate full content for instant client-side transitions
        $fullContent = $this->contentService->aggregateFullContent($parentEntity);
        $isFullView = false;
        $node = null;

        if ($childId && $childId !== 'full') {
            if (Str::isUuid($childId)) {
                $node = $parentEntity->nodes()->where('id', $childId)->first();
            }
            if (!$node) {
                $node = $parentEntity->nodes()->where('slug', $childId)->first();
            }

            if (!$node) {
                abort(404, 'Specific content node not found');
            }

            if (in_array($entityType, [EntityType::BOOK, EntityType::MANUSCRIPT])) {
                $currentEditorContent = $this->contentService->getBranchContent($parentEntity, $node->id);
            } else {
                $currentEditorContent = $node->content_html ?? $node->plain_text ?? '';
            }
        } else {
            // Default: Load FULL CONTENT
            $currentEditorContent = $fullContent;
            $isFullView = true;

            $node = $this->contentService->getFirstChild($parentEntity);
        }

        $entity = $parentEntity;

        // التحقق من الصلاحية
        Gate::authorize('update', $entity);

        // Load children directly from PostgreSQL relation
        $entity->load('children');

        // Load siblings for Manuscript if 'code' exists
        if ($entityType === EntityType::MANUSCRIPT && $entity->code) {
            $parts = explode('-', $entity->code);
            array_pop($parts);
            $workPrefix = implode('-', $parts);

            $siblings = Manuscript::where('code', 'LIKE', $workPrefix . '-%')
                ->where('id', '!=', $entity->id)
                ->with('children')
                ->get();

            $entity->setRelation('siblings', $siblings);
        }

        // Record last active session
        if (auth()->check()) {
            auth()->user()->update([
                'last_studio_type' => $type,
                'last_studio_slug' => $slug,
                'last_studio_child_id' => $childId ? $node?->id : null
            ]);
        }

        $data = $this->contentService->prepareEditorData($entity, $node?->slug);

        if ($node && !$isFullView) {
            $data['contentNode'] = $node;
        }

        // Map to Studio Props
        $studioProps = [
            'type' => $type,
            'entity' => $entity,
            'editorContent' => $currentEditorContent,
            'fullContent' => $fullContent,
            'isFullView' => $isFullView,
            'activeChildId' => $isFullView ? null : $node?->id,
            'title' => $entity->title . ' | Entity Studio',
            'visual_map' => ContentNodeType::getVisualMap($entityType),
            '_legacy' => $data
        ];

        return Inertia::render('Technologies/Studio/StudioLayout', $studioProps);
    }

    /**
     * حفظ المحتوى: /studio/{type}/{slug}/{childId?}/save
     */
    public function save(Request $request, string $type, string $slug, ?string $childId = null)
    {
        $request->validate([
            'content' => 'required',
            'html_content' => 'nullable|string',
            'json_content' => 'nullable|array',
            'plain_text' => 'nullable|string',
            'child_id' => 'nullable|string',
            'title' => 'nullable|string|max:255',
        ]);

        $childToSave = $childId ?: $request->input('child_id');

        $entityType = EntityType::tryFrom($type);
        if (!$entityType) {
            abort(404, 'Invalid entity type');
        }

        $parent = $this->resolveParentEntity($entityType, $slug);
        if (!$parent) {
            abort(404, 'Parent entity not found');
        }
        Gate::authorize('update', $parent);

        // --- HANDLE SMART SAVE (FULL VIEW) ---
        if ($childToSave === 'full') {
            return $this->handleFullViewSave($request, $entityType, $parent);
        }

        if (!$childToSave) {
            return response()->json(['error' => 'Child ID is required for saving'], 422);
        }

        // --- RESOLVE SPECIFIC NODE ---
        $node = null;
        if (Str::isUuid($childToSave)) {
            $node = $parent->nodes()->where('id', $childToSave)->first();
        }
        if (!$node) {
            $node = $parent->nodes()->where('slug', $childToSave)->first();
        }

        if (!$node) {
            return response()->json(['error' => 'Specific content node not found'], 404);
        }

        $updateData = [
            'last_editor_id' => $request->user()?->id
        ];

        if ($request->has('title')) {
            $updateData['title'] = $request->input('title');
        }

        if (is_array($request->input('content'))) {
            $payload = $request->input('content');
            $updateData['content_html'] = $payload['html'] ?? '';
            $updateData['content_json'] = $payload['json'] ?? [];
            $updateData['plain_text'] = $payload['text'] ?? strip_tags($updateData['content_html']);
        } else {
            $html = $request->input('html_content') ?? $request->input('content');
            $updateData['content_html'] = $html;
            $updateData['plain_text'] = $request->input('plain_text') ?? strip_tags($html);
            if ($request->has('json_content')) {
                $updateData['content_json'] = $request->input('json_content');
            }
        }

        $node->update($updateData);

        return response()->json([
            'message' => 'تم الحفظ بنجاح',
            'last_saved' => now()->toIso8601String()
        ]);
    }

    /**
     * معالجة الحفظ الذكي لوضع "كامل المحتوى"
     * يقوم بتقسيم النص المجمع وإمالة كل جزء لمقطعه الأصلي
     */
    protected function handleFullViewSave(Request $request, EntityType $type, $parent)
    {
        $html = null;
        if (is_array($request->input('content'))) {
            $html = $request->input('content')['html'] ?? '';
        } else {
            $html = $request->input('html_content') ?? $request->input('content');
        }

        if (empty($html)) {
            return response()->json(['error' => 'Content is required'], 422);
        }

        $query = $parent->nodes();
        if (in_array($type, [EntityType::AUDIO, EntityType::VIDEO])) {
            $query->orderByRaw("(metadata->>'start_time')::float NULLS LAST")->orderBy('order', 'asc');
        } else {
            $query->orderBy('order', 'asc');
        }
        $children = $query->get();

        if ($children->isEmpty()) {
            return response()->json(['message' => 'No segments found to update'], 200);
        }

        $segmentsData = $request->input('segments');
        $frontendDataMap = [];
        if (is_array($segmentsData)) {
            foreach ($segmentsData as $seg) {
                if (isset($seg['id'])) {
                    $frontendDataMap[(string) $seg['id']] = $seg;
                }
            }
        }

        $markerRegex = '/<h4[^>]*class="[^"]*structure-marker[^"]*"[^>]*>.*?<\/h4>/siu';
        preg_match_all($markerRegex, $html, $matches, PREG_OFFSET_CAPTURE);
        
        $htmlDataMap = [];
        $fullHtmlDataMap = [];
        $headerCount = count($matches[0]);
        
        for ($i = 0; $i < $headerCount; $i++) {
            $headerHtml = $matches[0][$i][0];
            $headerStart = $matches[0][$i][1];
            $headerEnd = $headerStart + strlen($headerHtml);
            
            preg_match('/data-id="(?P<id>[^"]+)"/i', $headerHtml, $idMatch);
            preg_match('/<h4[^>]*>(?P<title>.*?)<\/h4>/siu', $headerHtml, $titleMatch);
            
            $id = $idMatch['id'] ?? null;
            $title = $titleMatch['title'] ?? null;
            
            if (!$id) {
                continue;
            }

            $nextHeaderStart = ($i + 1 < $headerCount) ? $matches[0][$i + 1][1] : strlen($html);
            $content = substr($html, $headerEnd, $nextHeaderStart - $headerEnd);
            
            $content = trim($content);
            $content = preg_replace('/^<p><br\/><\/p>/', '', $content);
            $content = preg_replace('/<p><br\/><\/p>$/', '', $content);

            $htmlDataMap[(string) $id] = [
                'title' => trim($title, " :\t\n\r\0\x0B"),
                'header_found' => true,
                'content_length' => strlen($content)
            ];

            $fullHtmlDataMap[(string) $id] = $content;
        }

        if ($headerCount === 0 && count($children) === 1) {
            $child = $children[0];
            $childId = (string) $child->id;
            $fullHtmlDataMap[$childId] = $html;
            
            $fullDocJson = $request->input('json_content') ?? null;
            if (is_array($fullDocJson) && isset($fullDocJson['content'])) {
                $frontendDataMap[$childId]['json'] = $fullDocJson['content'];
            }
        }

        foreach ($children as $child) {
            $childId = (string) $child->id;
            
            $updateData = [
                'last_editor_id' => $request->user()?->id
            ];

            if (isset($frontendDataMap[$childId]['title'])) {
                $updateData['title'] = $frontendDataMap[$childId]['title'];
            } elseif (isset($htmlDataMap[$childId]['title'])) {
                $updateData['title'] = $htmlDataMap[$childId]['title'];
            }

            if (isset($fullHtmlDataMap[$childId])) {
                $contentToSave = $fullHtmlDataMap[$childId];
                $updateData['content_html'] = $contentToSave;
                $updateData['plain_text'] = strip_tags($contentToSave);
            }

            if (isset($frontendDataMap[$childId]['json'])) {
                $updateData['content_json'] = $frontendDataMap[$childId]['json'];
            }

            $child->update($updateData);
        }

        return response()->json([
            'message' => 'تم حفظ جميع الأجزاء بنجاح (Full View Sync)',
            'updated_count' => count($fullHtmlDataMap),
            'last_saved' => now()->toIso8601String()
        ]);
    }

    /**
     * استئناف العمل بمجرد الدخول: /resume
     */
    public function resume(Request $request)
    {
        $user = $request->user();

        if ($user && $user->last_studio_type && $user->last_studio_slug) {
            return redirect()->route('studio.show', [
                'type' => $user->last_studio_type,
                'slug' => $user->last_studio_slug,
                'childId' => $user->last_studio_child_id
            ]);
        }

        // Fallback: Check if we have any recently updated content
        $book = Book::first();

        if ($book) {
            return redirect()->route('studio.show', ['type' => EntityType::BOOK->value, 'slug' => $book->slug]);
        }

        return redirect()->route('dashboard');
    }

    /**
     * تراجع عن نسخة سابقة
     */
    public function restoreVersion(Request $request, string $type, string $slug, string $childId, int $versionIndex)
    {
        $entityType = EntityType::tryFrom($type);
        if (!$entityType) {
            abort(404, 'Invalid entity type');
        }

        $parent = $this->resolveParentEntity($entityType, $slug);
        if (!$parent) {
            abort(404);
        }
        Gate::authorize('update', $parent);

        $node = $parent->nodes()->where('id', $childId)->firstOrFail();
        $versions = $node->versions ?? [];

        if (!isset($versions[$versionIndex])) {
            return response()->json(['error' => 'Version not found'], 404);
        }

        $targetVersion = $versions[$versionIndex];
        
        $node->update([
            'content_html' => $targetVersion['content_html'] ?? $node->content_html,
            'plain_text' => $targetVersion['plain_text'] ?? strip_tags($targetVersion['content_html'] ?? ''),
            'content_json' => $targetVersion['content_json'] ?? $targetVersion['content_blocks'] ?? $node->content_json,
            'last_editor_id' => $request->user()?->id
        ]);

        return response()->json([
            'message' => 'تم استرجاع النسخة بنجاح',
            'restored_content' => $node->content_html
        ]);
    }

    protected function getForeignKey(EntityType $type): string
    {
        return 'entity_id';
    }

    protected function getContentModelClass(EntityType $type): string
    {
        return ContentNode::class;
    }

    protected function resolveEntity(EntityType $type, string $slug): Entity
    {
        $entityModel = $type->modelClass();
        $node = ContentNode::where('slug', $slug)->firstOrFail();
        $entity = $entityModel::findOrFail($node->entity_id);
        $entity->load('children');
        return $entity;
    }

    protected function resolveParentEntity(EntityType $type, string $slug)
    {
        $modelClass = $type->modelClass();
        return $modelClass::where('slug', $slug)->first();
    }
}
