<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\ContentNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EntityContentService
{
    /**
     * الأنواع المسموحة لكل كيان (Allowed Content Types)
     * دستور المحتوى لتنظيم وهيكلة البيانات في PostgreSQL
     */
    protected array $allowedTypes = [
        'book' => ['sub-book', 'part', 'bab', 'chapter', 'masalah', 'page', 'section', 'paragraph'],
        'manuscript' => ['sub-book', 'part', 'bab', 'chapter', 'masalah', 'page', 'section', 'folio', 'paragraph'],
        'audio' => ['segment', 'track', 'marker', 'paragraph'],
        'video' => ['segment', 'scene', 'shot', 'paragraph'],
    ];

    /**
     * تحديد Model الموحد
     */
    public function getContentModel(Entity $entity): string
    {
        return ContentNode::class;
    }

    /**
     * إضافة عقدة جديدة (Add Node)
     */
    public function addNode(Entity $entity, string $type, string $title, $time = null, ?string $parentId = null): ContentNode
    {
        $maxOrder = $this->getMaxOrder($entity);
        
        $data = [
            'type' => $type,
            'title' => $title,
            'order' => $maxOrder + 1,
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'parent_id' => $parentId,
        ];
        
        if ($time !== null) {
            $data['start_time'] = (float) $time;
            $data['end_time'] = (float) $time + 10;
        }
        
        return $this->createNode($entity, $data);
    }

    /**
     * إنشاء محتوى جديد للكيان
     */
    public function createNode(Entity $entity, array $data): ContentNode
    {
        $entityType = strtolower(class_basename($entity));
        $contentType = $data['type'] ?? null;

        if (!$contentType) {
            throw ValidationException::withMessages(['type' => 'Content type is required']);
        }

        $allowed = $this->allowedTypes[$entityType] ?? [];

        if (empty($allowed)) {
            throw ValidationException::withMessages([
                'entity_type' => "Entity type '{$entityType}' is not configured for content creation."
            ]);
        }

        if (!in_array($contentType, $allowed)) {
            throw ValidationException::withMessages([
                'type' => "Content type '{$contentType}' is not allowed for '{$entityType}'. Allowed: " . implode(', ', $allowed)
            ]);
        }

        // التعامل مع start_time / end_time / folio_number في metadata
        $metadata = $data['metadata'] ?? [];
        if (isset($data['start_time'])) {
            $metadata['start_time'] = (float) $data['start_time'];
            unset($data['start_time']);
        }
        if (isset($data['end_time'])) {
            $metadata['end_time'] = (float) $data['end_time'];
            unset($data['end_time']);
        }
        if (isset($data['folio_number'])) {
            $metadata['folio_number'] = $data['folio_number'];
            unset($data['folio_number']);
        }
        if (isset($data['image_url'])) {
            $metadata['image_url'] = $data['image_url'];
            unset($data['image_url']);
        }
        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }

        // التعامل مع محتوى النص (HTML & Plain Text)
        if (isset($data['content']) && !isset($data['content_html'])) {
            $data['content_html'] = $data['content'];
            $data['plain_text'] = strip_tags($data['content']);
            unset($data['content']);
        } elseif (isset($data['content_html']) && !isset($data['plain_text'])) {
            $data['plain_text'] = strip_tags($data['content_html']);
        }

        /** @var ContentNode $node */
        $node = $entity->nodes()->create($data);
        return $node;
    }

    /**
     * جلب عقدة محددة عبر الـ slug أو الـ id
     */
    public function getNode(Entity $entity, string $identifier): ?ContentNode
    {
        return $entity->nodes()
            ->where(function ($query) use ($identifier) {
                $query->where('slug', $identifier);
                if (Str::isUuid($identifier)) {
                    $query->orWhere('id', $identifier);
                }
            })
            ->first();
    }

    /**
     * جلب عقدة عبر الـ ID
     */
    public function getNodeById(Entity $entity, string $id): ContentNode
    {
        return $entity->nodes()
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * جلب الهيكلية (Hierarchy)
     */
    public function getHierarchy(Entity $entity, ?int $limit = null): Collection
    {
        $query = $entity->nodes()
            ->select(['id', 'title', 'slug', 'type', 'order', 'parent_id', 'metadata', 'content_html']);

        if (in_array(class_basename($entity), ['Audio', 'Video'])) {
            $query->orderByRaw("(metadata->>'start_time')::float NULLS LAST")->orderBy('order', 'asc');
        } else {
            $query->orderBy('order', 'asc');
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * جلب التنقل (prev/next)
     */
    public function getNavigation(Entity $entity, ContentNode $currentNode): array
    {
        $order = $currentNode->order;
        if ($order === null) {
            return ['prev' => null, 'next' => null];
        }

        $prev = $entity->nodes()
            ->where('order', '<', (int) $order)
            ->orderBy('order', 'desc')
            ->first(['slug', 'title']);

        $next = $entity->nodes()
            ->where('order', '>', (int) $order)
            ->orderBy('order', 'asc')
            ->first(['slug', 'title']);

        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * تحضير بيانات المحرر (Data Preparation)
     */
    public function prepareEditorData(Entity $entity, ?string $slug = null): array
    {
        $node = null;
        if ($slug) {
            try {
                $node = $this->getNode($entity, $slug);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $node = $this->getFirstChild($entity);
            }
        } else {
            $node = $this->getFirstChild($entity);
        }

        $hierarchy = $this->getHierarchy($entity, 500);
        $navigation = $node ? $this->getNavigation($entity, $node) : ['prev' => null, 'next' => null];

        $resourceData = [
            'id' => $entity->id,
            'title' => $entity->title,
            'slug' => $entity->slug,
            'type' => strtolower(class_basename($entity)),
            'url' => $entity->file_path ? asset('storage/' . $entity->file_path) : null,
        ];

        $type = class_basename($entity);
        if ($type === 'Audio' || $type === 'Video') {
            $resourceData['duration'] = $entity->duration ?? 0;
        }

        if (in_array($type, ['Manuscript', 'Audio', 'Video'])) {
            $versions = $entity->versions()->with('publisher')->get();

            $resourceData['versions'] = $versions->map(function ($v) {
                $title = "الإصدار " . ($v->edition_number ?? '1');
                if ($v->title && (str_contains($v->title, 'النسخة') || str_contains($v->title, 'تسجيل'))) {
                    $title = $v->title;
                }

                if ($v->publisher) {
                    $title .= " - " . $v->publisher->name;
                }

                return [
                    'title' => $title,
                    'url' => $v->file_path ? asset('storage/' . $v->file_path) : null
                ];
            })->toArray();

            if (empty($resourceData['versions']) && $entity->file_path && $type !== 'Manuscript') {
                $resourceData['versions'][] = [
                    'title' => 'الملف الأساسي',
                    'url' => asset('storage/' . $entity->file_path)
                ];
            }
        }

        return [
            'entity' => $entity,
            'contentNode' => $node,
            'hierarchy' => $hierarchy,
            'navigation' => $navigation,
            'editor_mode' => strtolower($type),
            'resource_data' => $resourceData
        ];
    }

    /**
     * جلب أول عقدة تابعة للكيان
     */
    public function getFirstChild(Entity $entity): ?ContentNode
    {
        $query = $entity->nodes();

        if (in_array(class_basename($entity), ['Audio', 'Video'])) {
            $query->orderByRaw("(metadata->>'start_time')::float NULLS LAST")->orderBy('order', 'asc');
        } else {
            $query->orderBy('order', 'asc');
        }

        return $query->first();
    }

    /**
     * جلب أعلى قيمة للترتيب (Max Order)
     */
    public function getMaxOrder(Entity $entity): int
    {
        return (int) $entity->nodes()->max('order') ?? 0;
    }

    /**
     * تجميع كافة محتويات الأبناء في نص واحد (Full Transcript)
     */
    public function aggregateFullContent(Entity $entity): string
    {
        $supportsHierarchy = in_array(class_basename($entity), ['Book', 'Manuscript']);
        
        if ($supportsHierarchy) {
            $rootNodes = $entity->nodes()
                ->whereNull('parent_id')
                ->orderBy('order', 'asc')
                ->get();

            $fullTranscript = '';
            foreach ($rootNodes as $node) {
                $fullTranscript .= $this->renderNodeWithDescendants($entity, $node);
            }
            return $fullTranscript;
        } else {
            $allNodes = $entity->nodes()
                ->orderByRaw("(metadata->>'start_time')::float NULLS LAST")
                ->orderBy('order', 'asc')
                ->get();
            
            $fullTranscript = '';
            
            foreach ($allNodes as $node) {
                $title = $node->title ?: "قسم";
                $level = 'h4';
                
                $marker = "<{$level} class=\"structure-marker\" ";
                $marker .= "data-segment-link=\"true\" ";
                $marker .= "data-id=\"{$node->id}\" ";
                $marker .= "data-type=\"{$node->type}\" ";
                if ($node->start_time !== null) {
                    $marker .= "data-start-time=\"{$node->start_time}\" ";
                }
                $marker .= ">{$title}</{$level}>";
                
                $fullTranscript .= $marker;
                $content = $node->content_html ?? $node->plain_text ?? '';
                if ($node->entity_type === 'video' && empty($content) && $node->description) {
                    $content = "<p>{$node->description}</p>";
                }
                
                $fullTranscript .= $content;
                $fullTranscript .= "<p><br/></p>";
            }
            return $fullTranscript;
        }
    }

    protected function renderNodeWithDescendants(Entity $entity, ContentNode $node): string
    {
        $title = $node->title ?: "قسم";
        $level = $this->getHeadingLevel($node);
        $tagClass = ($node->type === 'paragraph') ? 'structure-marker-text' : 'structure-marker';
        
        $marker = "<{$level} class=\"{$tagClass}\" ";
        $marker .= "data-id=\"{$node->id}\" ";
        $marker .= "data-type=\"{$node->type}\" ";
        $marker .= ">";
        $marker .= $title;
        $marker .= "</{$level}>";
        
        $html = $marker;
        $content = $node->content_html ?? $node->plain_text ?? '';
        if ($node->entity_type === 'video' && empty($content) && $node->description) {
            $content = "<p>{$node->description}</p>";
        }
        $html .= $content;
        $html .= "<p><br/></p>";

        $children = $node->children()->orderBy('order', 'asc')->get();
        foreach ($children as $child) {
            $html .= $this->renderNodeWithDescendants($entity, $child);
        }

        return $html;
    }

    /**
     * تحديث محتوى عقدة
     */
    public function updateContent(Entity $entity, $payloadOrContent): bool
    {
        return true;
    }

    /**
     * حذف عقدة محتوى (Node)
     */
    public function deleteNode(Entity $entity, string $nodeId): bool
    {
        $node = $this->getNode($entity, $nodeId);
        if (!$node) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }

        return (bool) $node->delete();
    }

    /**
     * حساب عمق العقدة في الهيكل الشجري
     */
    public function getDepth(ContentNode $node): int
    {
        $depth = 0;
        $current = $node;

        while ($current->parent_id) {
            $depth++;
            $parent = $current->parent;
            if (!$parent) break;
            $current = $parent;
        }

        return $depth;
    }

    /**
     * تحديد مستوى العنوان (H2-H6) بناءً على العمق والنوع
     */
    public function getHeadingLevel(ContentNode $node): string
    {
        if ($node->type === 'paragraph') {
            return 'p';
        }

        $depth = $this->getDepth($node);
        $level = $depth + 2;

        return $level > 6 ? 'h6' : "h{$level}";
    }

    /**
     * جلب كافة المحتوى لفرع شجري بالكامل (Node + Descendants)
     */
    public function getBranchContent(Entity $entity, string $nodeId): string
    {
        $rootNode = $this->getNode($entity, $nodeId);
        if (!$rootNode) return '';

        $allNodes = $this->collectDescendants($rootNode);
        $allNodes->prepend($rootNode);

        $html = '';
        foreach ($allNodes as $node) {
            $level = $this->getHeadingLevel($node);
            $tagClass = $level === 'p' ? 'structure-marker-text' : 'structure-marker';
            
            $marker = "<{$level} class=\"{$tagClass}\" ";
            $marker .= "data-segment-link=\"true\" ";
            $marker .= "data-id=\"{$node->id}\" ";
            $marker .= "data-type=\"{$node->type}\" ";
            if ($node->start_time !== null) {
                $marker .= "data-start-time=\"{$node->start_time}\" ";
            }
            $marker .= ">{$node->title}</{$level}>";

            $html .= $marker;
            $html .= $node->content_html ?? $node->plain_text ?? '';
            $html .= "<p><br/></p>";
        }

        return $html;
    }

    /**
     * جمع كافة الأبناء والأحفاد تتابعياً (Recursive Collection)
     */
    protected function collectDescendants(ContentNode $node): Collection
    {
        $descendants = collect();

        foreach ($node->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->collectDescendants($child));
        }

        return $descendants;
    }
}
