<?php

namespace App\Http\Controllers;

use App\Models\Audio;
use Illuminate\Http\Request;
use App\Services\MediaManagerService;
use Illuminate\Database\Eloquent\Model;

/**
 * AudioController - Highly simplified using EntityController Hooks
 */
class AudioController extends EntityController
{
    use Traits\HasEditor;

    //Configuration
    protected function getModelClass(): string { return Audio::class; }
    protected function getViewPath(): string { return 'Audios'; }
    protected function getRouteName(): string { return 'audios'; }
    protected function getStoreRequestClass(): ?string { return \App\Http\Requests\StoreAudioRequest::class; }
    protected function getUpdateRequestClass(): ?string { return \App\Http\Requests\UpdateAudioRequest::class; }

    //Customization
    protected function getRelations(): array { return ['tags', 'categories', 'authors', 'versions.publisher', 'comments.user']; }
    protected function getSearchFields(): array { return ['title']; }
    protected function getSearchRelations(): array { return ['authors' => 'name']; }
    protected function getPerPage(): int { return 16; }
    protected function getFileUploads(): array { return ['file' => 'audio', 'cover' => 'covers']; }
    protected function shouldLoadFirstChild(): bool { return true; }
    
    protected function getCreateSuccessMessage(): string { return 'تم إنشاء الملف الصوتي بنجاح'; }
    protected function getUpdateSuccessMessage(): string { return 'تم تحديث الملف الصوتي بنجاح'; }
    protected function getDeleteSuccessMessage(): string { return 'تم حذف الملف الصوتي بنجاح'; }

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
        $data['type'] = 'audio';
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
