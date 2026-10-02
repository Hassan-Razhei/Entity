<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use App\Services\MediaManagerService;
use Illuminate\Database\Eloquent\Model;

/**
 * BookController - Highly simplified using EntityController Hooks
 */
class BookController extends EntityController
{
    use Traits\HasEditor;

    // ========================================
    // CONFIGURATION
    // ========================================

    protected function getModelClass(): string { return Book::class; }
    protected function getViewPath(): string { return 'Books'; }
    protected function getRouteName(): string { return 'books'; }
    protected function getStoreRequestClass(): ?string { return \App\Http\Requests\StoreBookRequest::class; }
    protected function getUpdateRequestClass(): ?string { return \App\Http\Requests\UpdateBookRequest::class; }

    //Customization
    protected function getRelations(): array { return ['tags', 'categories', 'authors', 'versions.publisher', 'comments.user']; }
    protected function getSearchFields(): array { return ['title']; }
    protected function getSearchRelations(): array { return ['authors' => 'name']; }
    protected function getPerPage(): int { return 16; }
    protected function getFileUploads(): array { return ['file' => 'books', 'cover' => 'covers']; }
    protected function shouldLoadFirstChild(): bool { return true; }

    protected function getCreateSuccessMessage(): string { return 'تم إنشاء الكتاب بنجاح'; }
    protected function getUpdateSuccessMessage(): string { return 'تم تحديث الكتاب بنجاح'; }
    protected function getDeleteSuccessMessage(): string { return 'تم حذف الكتاب بنجاح'; }

    protected function getFormData(): array
    {
        return [
            'authors' => \App\Models\Author::orderBy('name')->get(['id', 'name']),
            'publishers' => \App\Models\Publisher::orderBy('name')->get(['id', 'name']),
            'categories' => \App\Models\Category::orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function getSyncableRelations(): array { return ['categories', 'tags']; }

    // ========================================
    // HOOKS
    // ========================================

    /**
     * Use MediaManagerService for persistence
     */
    protected function persistModel(Model $model, array $data, Request $request): void
    {
        /** @var \App\Models\Entity $model */
        $data['type'] = 'book';
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
