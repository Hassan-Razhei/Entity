<?php

namespace App\Services;

use App\Enums\EntityType;
use App\Models\Book;
use App\Models\Entity;
use App\Models\Version;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MediaManagerService
{
    protected EntityManagerService $entityManager;

    public function __construct(EntityManagerService $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create a new media entity with its initial version and authors.
     *
     * @param array $data
     * @return Entity
     * @throws ValidationException
     */
    public function createMedia(array $data): Entity
    {
        $this->validateMediaData($data);
        $type = $data['type'];

        return DB::transaction(function () use ($data, $type) {
            // 1. Prepare and create base entity
            $entityData = $this->prepareEntityData($data, $type);
            $entity = $this->entityManager->create($entityData);

            // 2. Attach Authors
            if (!empty($data['author_ids'])) {
                $entity->authors()->sync($data['author_ids']);
            }

            // 3. Create the Initial Version
            $versionData = $this->prepareVersionData($data, $entity);
            $entity->versions()->create($versionData);

            return $entity->fresh(['authors', 'versions']);
        });
    }

    public function updateMedia(Entity $entity, array $data): Entity
    {
        $this->validateMediaData($data);

        return DB::transaction(function () use ($entity, $data) {
            // 1. Update basic entity details
            $entityData = $this->prepareEntityData($data, $entity->type);
            $this->entityManager->update($entity, $entityData);

            // 2. Sync Authors
            if (isset($data['author_ids'])) {
                $entity->authors()->sync($data['author_ids']);
            }

            // 3. Update or Create the Primary Version
            $versionData = $this->prepareVersionData($data, $entity);
            
            /** @var Version|null $version */
            $version = $entity->versions()->first();
            
            if ($version) {
                $version->update($versionData);
            } elseif (isset($data['file_path'])) {
                $entity->versions()->create($versionData);
            }

            return $entity->fresh(['authors', 'versions']);
        });
    }

    protected function prepareEntityData(array $data, string $type): array
    {
        $entityData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ];

        if (isset($data['type'])) {
            $entityData['type'] = $data['type'];
        }

        if (isset($data['cover_path'])) {
            $entityData['cover_path'] = $data['cover_path'];
        }

        if (isset($data['file_path'])) {
            $entityData['file_path'] = $data['file_path'];
        }

        if (isset($data['code'])) {
            $entityData['code'] = $data['code'];
        }

        // Type-specific attributes
        $entityTypeEnum = EntityType::tryFrom($type);
        
        if ($entityTypeEnum === EntityType::MANUSCRIPT) {
            $manuscriptFields = [
                'manuscript_century', 'manuscript_century_label', 'original_title', 'catalog_number', 'scribe', 
                'copy_date', 'parts', 'script_type', 'dimensions', 'lines_per_page', 
                'inscriptions', 'notes', 'pages', 'manuscript_start', 'manuscript_end', 'is_autograph'
            ];
            
            foreach ($manuscriptFields as $field) {
                if (isset($data[$field])) {
                    $entityData[$field] = $data[$field];
                }
            }
        } elseif ($entityTypeEnum?->supportsDuration() && isset($data['duration'])) {
            $entityData['duration'] = $data['duration'];
        }

        return $entityData;
    }

    protected function prepareVersionData(array $data, Entity $entity): array
    {
        return [
            'versionable_id' => $entity->id,
            'versionable_type' => $entity->getMorphClass(),
            'file_path' => $data['file_path'] ?? null,
            'publisher_id' => $data['publisher_id'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            'pages' => $data['pages'] ?? null,
            'published_year' => $data['published_year'] ?? null,
            'edition_number' => $data['edition_number'] ?? 1,
            'format' => $data['format'] ?? (EntityType::tryFrom($entity->type)?->defaultFormat() ?? 'mp4'),
            'file_size' => $data['file_size'] ?? 0,
        ];
    }

    protected function validateMediaData(array $data): void
    {
        $validator = Validator::make($data, [
            'title' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'type' => 'sometimes|required|string|in:' . implode(',', EntityType::values()),
            'file_path' => 'nullable|string',
            'author_ids' => 'nullable|array',
            'author_ids.*' => 'exists:authors,id',
            'publisher_id' => 'nullable|uuid|exists:publishers,id',
            'isbn' => 'nullable|string|max:20',
            'duration' => 'nullable|integer',
            'manuscript_century' => 'nullable|string|max:100',
            'manuscript_century_label' => 'nullable|string|max:100',
            'pages' => 'nullable|integer',
            'published_year' => 'nullable|integer',
            // Manuscript specifics
            'catalog_number' => 'nullable|string|max:100',
            'scribe' => 'nullable|string|max:255',
            'original_title' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
