<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use App\Services\MediaManagerService;
use Illuminate\Database\Eloquent\Model;

/**
 * VideoController - Highly simplified using EntityController Hooks
 */
class VideoController extends EntityController
{
    use Traits\HasEditor;

    //Configuration
    protected function getModelClass(): string { return Video::class; }
    protected function getViewPath(): string { return 'Videos'; }
    protected function getRouteName(): string { return 'videos'; }
    protected function getStoreRequestClass(): ?string { return \App\Http\Requests\StoreVideoRequest::class; }
    protected function getUpdateRequestClass(): ?string { return \App\Http\Requests\UpdateVideoRequest::class; }

    //Customization
    protected function getRelations(): array { return ['tags', 'categories', 'authors', 'versions.publisher', 'comments.user']; }
    protected function getSearchFields(): array { return ['title']; }
    protected function getSearchRelations(): array { return ['authors' => 'name']; }
    protected function getPerPage(): int { return 16; }
    protected function getFileUploads(): array { return ['file' => 'videos', 'cover' => 'covers']; }
    protected function shouldLoadFirstChild(): bool { return true; }

    protected function getCreateSuccessMessage(): string { return 'تم إنشاء الفيديو بنجاح'; }
    protected function getUpdateSuccessMessage(): string { return 'تم تحديث الفيديو بنجاح'; }
    protected function getDeleteSuccessMessage(): string { return 'تم حذف الفيديو بنجاح'; }

    protected function getFormData(): array
    {
        return [
            'authors' => \App\Models\Author::orderBy('name')->get(['id', 'name']),
            'publishers' => \App\Models\Publisher::orderBy('name')->get(['id', 'name']),
            'categories' => \App\Models\Category::orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function getSyncableRelations(): array { return ['categories', 'tags']; }

    /**
     * Hook: Use MediaManagerService for persistence
     */
    protected function persistModel(Model $model, array $data, Request $request): void
    {
        /** @var \App\Models\Entity $model */
        $data['type'] = 'video';
        $manager = app(MediaManagerService::class);
        
        if ($model->exists) {
            $updated = $manager->updateMedia($model, $data);
            $model->setRawAttributes($updated->getAttributes(), true);
        } else {
            $created = $manager->createMedia($data);
            $model->setRawAttributes($created->getAttributes(), true);
            $model->exists = true;
        }
    }
}
