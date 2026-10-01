<?php

namespace App\Http\Controllers\Api;

use App\Enums\EntityType;
use App\Enums\ContentNodeType;
use App\Http\Controllers\Controller;
use App\Services\EntityContentService;
use App\Models\ContentNode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SegmentController extends Controller
{
    protected $contentService;

    public function __construct(EntityContentService $contentService)
    {
        $this->contentService = $contentService;
    }

    /**
     * Store a new segment/scene for audio/video
     */
    public function store(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|string',
            'entity_type' => 'required|in:audio,video',
            'title' => 'required|string|max:255',
            'start_time' => 'nullable|numeric|min:0',
            'end_time' => 'nullable|numeric|min:0',
            'file_path' => 'nullable|string',
        ]);

        $modelClass = match ($request->entity_type) {
            'audio' => \App\Models\Audio::class,
            'video' => \App\Models\Video::class,
        };

        $entity = $modelClass::findOrFail($request->entity_id);

        $entityType = EntityType::from($request->entity_type);
        $type = ContentNodeType::defaultFor($entityType)->value;

        $startTime = (float) ($request->start_time ?? 0);

        $existingSegments = $entity->nodes()
            ->reorder()
            ->orderByRaw("(metadata->>'start_time')::float NULLS LAST")
            ->orderBy('order', 'asc')
            ->get();

        $newOrder = 1;
        foreach ($existingSegments as $index => $seg) {
            if ($startTime < ($seg->start_time ?? 0)) {
                $newOrder = $index + 1;
                break;
            }
            $newOrder = $index + 2;
        }

        $entity->nodes()
            ->where('order', '>=', $newOrder)
            ->increment('order');

        $segment = $this->contentService->createNode($entity, [
            'type' => $type,
            'title' => $request->title,
            'slug' => \App\Helpers\SlugHelper::generate($request->title) . '-' . Str::random(8),
            'content_html' => '<p></p>',
            'start_time' => $startTime,
            'end_time' => $request->end_time ?? ($startTime + 10),
            'order' => $newOrder,
        ]);

        return response()->json([
            'message' => 'تم إنشاء المقطع بنجاح',
            'segment' => $segment
        ], 201);
    }

    /**
     * Update a segment
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'entity_id' => 'required|string',
            'entity_type' => 'required|in:audio,video',
            'title' => 'nullable|string|max:255',
            'start_time' => 'nullable|numeric|min:0',
        ]);

        $modelClass = match ($request->entity_type) {
            'audio' => \App\Models\Audio::class,
            'video' => \App\Models\Video::class,
        };

        try {
            $entity = $modelClass::findOrFail($request->entity_id);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Parent not found'], 404);
        }

        $segment = $this->contentService->getNode($entity, $id);

        if (!$segment) {
            return response()->json(['error' => 'Segment not found'], 404);
        }

        $updateData = [];

        if ($request->has('title')) {
            $updateData['title'] = $request->title;
        }

        if ($request->has('start_time')) {
            $newStartTime = (float) $request->start_time;
            $oldStartTime = $segment->start_time ?? 0;

            if (abs($newStartTime - $oldStartTime) > 0.1) {
                $otherSegments = $entity->nodes()
                    ->where('id', '!=', $segment->id)
                    ->reorder()
                    ->orderByRaw("(metadata->>'start_time')::float NULLS LAST")
                    ->orderBy('order', 'asc')
                    ->get();

                $newOrder = 1;
                foreach ($otherSegments as $index => $seg) {
                    if ($newStartTime < ($seg->start_time ?? 0)) {
                        $newOrder = $index + 1;
                        break;
                    }
                    $newOrder = $index + 2;
                }

                $entity->nodes()
                    ->where('order', '>', $segment->order)
                    ->decrement('order');

                $entity->nodes()
                    ->where('order', '>=', $newOrder)
                    ->increment('order');

                $updateData['start_time'] = $newStartTime;
                $updateData['order'] = $newOrder;
            }
        }

        $segment->update($updateData);
        $segment->refresh();

        return response()->json([
            'message' => 'تم تحديث المقطع بنجاح',
            'segment' => $segment
        ]);
    }

    /**
     * Delete a segment
     */
    public function destroy(Request $request, string $id)
    {
        $request->validate([
            'entity_id' => 'required|string',
            'entity_type' => 'required|in:audio,video',
        ]);

        $modelClass = match ($request->entity_type) {
            'audio' => \App\Models\Audio::class,
            'video' => \App\Models\Video::class,
        };

        $entity = $modelClass::findOrFail($request->entity_id);

        $this->contentService->deleteNode($entity, $id);

        return response()->json([
            'message' => 'تم حذف المقطع بنجاح'
        ]);
    }
}
