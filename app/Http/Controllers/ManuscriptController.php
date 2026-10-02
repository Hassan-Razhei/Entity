<?php

namespace App\Http\Controllers;

use App\Models\Manuscript;
use Illuminate\Http\Request;
use App\Services\MediaManagerService;
use Illuminate\Database\Eloquent\Model;

/**
 * ManuscriptController - Highly simplified using EntityController Hooks
 */
class ManuscriptController extends EntityController
{
    use Traits\HasEditor;

    //Configuration
    protected function getModelClass(): string { return Manuscript::class; }
    protected function getViewPath(): string { return 'Manuscripts'; }
    protected function getRouteName(): string { return 'manuscripts'; }
    protected function getStoreRequestClass(): ?string { return \App\Http\Requests\StoreManuscriptRequest::class; }
    protected function getUpdateRequestClass(): ?string { return \App\Http\Requests\UpdateManuscriptRequest::class; }

    //Customization
    protected function getRelations(): array { return ['tags', 'categories', 'authors', 'versions.publisher', 'comments.user']; }
    protected function getSearchFields(): array { return ['title']; }
    protected function getSearchRelations(): array { return ['authors' => 'name']; }
    protected function getPerPage(): int { return 16; }
    protected function getFileUploads(): array { return ['file' => 'manuscripts', 'cover' => 'covers']; }
    protected function shouldLoadFirstChild(): bool { return true; }

    protected function getCreateSuccessMessage(): string { return 'تم إنشاء المخطوطة بنجاح'; }
    protected function getUpdateSuccessMessage(): string { return 'تم تحديث المخطوطة بنجاح'; }
    protected function getDeleteSuccessMessage(): string { return 'تم حذف المخطوطة بنجاح'; }

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
        $data['type'] = 'manuscript';
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
    /**
     * View manuscript in the immersive sandbox
     */
    public function sandbox(Manuscript $manuscript): \Inertia\Response
    {
        $manuscript->load(['children' => fn($q) => $q->orderBy('order')]);
        
        $siblings = [];
        if ($manuscript->code) {
             $siblings = Manuscript::where('code', $manuscript->code)
                ->where('id', '!=', $manuscript->id)
                ->with(['children' => fn($q) => $q->orderBy('order')])
                ->get();
        }

        return \Inertia\Inertia::render('Technologies/Manuscripter/Sandbox', [
            'manuscript' => $manuscript,
            'siblings' => $siblings,
        ]);
    }
}
