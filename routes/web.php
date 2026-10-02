<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookContentController;
use App\Http\Controllers\BookerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DeletionController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ManuscriptController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PublisherController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\ShelfController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [App\Http\Controllers\Auth\LoginController::class, 'create'])->name('login');
    Route::post('login', [App\Http\Controllers\Auth\LoginController::class, 'store']);

    Route::get('register', [App\Http\Controllers\Auth\RegisterController::class, 'create'])->name('register');
    Route::post('register', [App\Http\Controllers\Auth\RegisterController::class, 'store']);
});

Route::post('logout', [App\Http\Controllers\Auth\LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Media Streaming Routes (with HTTP Range Request support for seeking)
Route::get('/stream/videos/{path}', [App\Http\Controllers\MediaStreamController::class, 'streamVideo'])
    ->where('path', '.*')
    ->name('stream.video');
    
Route::get('/stream/audio/{path}', [App\Http\Controllers\MediaStreamController::class, 'streamAudio'])
    ->where('path', '.*')
    ->name('stream.audio');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [GlobalSearchController::class, 'index'])->name('search');

    // Unified Smart Editor Routes
    // Unified Smart Editor Routes (Entity Studio)
    Route::get('/studio/resume', [App\Http\Controllers\UnifiedEditorController::class, 'resume'])->name('studio.resume');
    Route::get('/studio/{type}/{slug}/{childId?}', [App\Http\Controllers\UnifiedEditorController::class, 'show'])->name('studio.show');
    Route::post('/studio/{type}/{slug}/{childId?}/save', [App\Http\Controllers\UnifiedEditorController::class, 'save'])->name('studio.save');
    Route::post('/studio/{type}/{slug}/{childId}/restore/{versionIndex}', [App\Http\Controllers\UnifiedEditorController::class, 'restoreVersion'])->name('studio.restore');
    Route::post('/studio/{type}/{slug}/nodes', [App\Http\Controllers\ContentNodeController::class, 'store'])->name('studio.nodes.store');

    // Missing API routes for Book Children (Compatibility Layer)
    Route::post('api/book-children/{id}/save', [BookContentController::class, 'updateValidation'])->name('api.book-children.save');
    Route::post('api/book-children/{id}/restore/{version?}', [BookContentController::class, 'restoreVersion'])->name('api.book-children.restore');

    // API routes for Segments (Audio/Video)
    Route::post('api/segments', [App\Http\Controllers\Api\SegmentController::class, 'store'])->name('api.segments.store');
    Route::put('api/segments/{id}', [App\Http\Controllers\Api\SegmentController::class, 'update'])->name('api.segments.update');
    Route::delete('api/segments/{id}', [App\Http\Controllers\Api\SegmentController::class, 'destroy'])->name('api.segments.destroy');

    // System Commands API
    Route::post('api/system/run-command', [App\Http\Controllers\SystemController::class, 'runCommand'])->name('api.system.run-command');
    Route::post('api/system/list-files', [App\Http\Controllers\SystemController::class, 'listFiles'])->name('api.system.list-files');

    // Command Dashboard Page
    Route::get('/system/commands', function () {
        return Inertia\Inertia::render('System/Commands');
    })->name('system.commands');

    // Editor Test Route
    Route::get('/editor-test', [App\Http\Controllers\EditorTestController::class, 'index'])->name('editor.test');


    // Web Resource Routes
    Route::resource('books', BookController::class);
    Route::resource('audios', AudioController::class);
    Route::get('audios/{audio}/editor/{child}', [AudioController::class, 'editor'])->name('audios.editor');

    Route::resource('videos', VideoController::class);
    Route::get('videos/{video}/editor/{child}', [VideoController::class, 'editor'])->name('videos.editor');

    Route::resource('manuscripts', ManuscriptController::class);
    Route::get('manuscripts/{manuscript}/editor/{child}', [ManuscriptController::class, 'editor'])->name('manuscripts.editor');

    // Book Reader & Editor Routes
    Route::get('books/{book}/reader/{child?}', [BookContentController::class, 'show'])->name('books.reader');
    Route::get('books/{book}/editor/{child}', [BookController::class, 'editor'])->name('books.editor');
    Route::get('book-contents/{child}', [BookContentController::class, 'getChildContent'])->name('book-contents.show');
    // routes/web.php or routes/api.php

    Route::get('authors', [AuthorController::class, 'index'])->name('authors.index');
    // Taxonomy and Organization
    Route::post('categories/bulk-destroy', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
    Route::resource('categories', CategoryController::class);

    Route::post('tags/bulk-destroy', [TagController::class, 'bulkDestroy'])->name('tags.bulk-destroy');
    Route::resource('tags', TagController::class);

    Route::post('authors/bulk-destroy', [AuthorController::class, 'bulkDestroy'])->name('authors.bulk-destroy');
    Route::post('authors/{author}/restore', [AuthorController::class, 'restore'])->name('authors.restore');
    Route::delete('authors/{author}/force-delete', [AuthorController::class, 'forceDelete'])->name('authors.force-delete');
    Route::resource('authors', AuthorController::class);

    Route::post('publishers/bulk-destroy', [PublisherController::class, 'bulkDestroy'])->name('publishers.bulk-destroy');
    Route::resource('publishers', PublisherController::class);

    Route::post('bookers/bulk-destroy', [BookerController::class, 'bulkDestroy'])->name('bookers.bulk-destroy');
    Route::resource('bookers', BookerController::class);

    Route::post('topics/bulk-destroy', [TopicController::class, 'bulkDestroy'])->name('topics.bulk-destroy');
    Route::resource('topics', TopicController::class);

    Route::post('languages/bulk-destroy', [LanguageController::class, 'bulkDestroy'])->name('languages.bulk-destroy');
    Route::resource('languages', LanguageController::class);

    Route::post('shelves/bulk-destroy', [ShelfController::class, 'bulkDestroy'])->name('shelves.bulk-destroy');
    Route::resource('shelves', ShelfController::class);

    Route::resource('collections', CollectionController::class);
    Route::post('series/bulk-destroy', [SeriesController::class, 'bulkDestroy'])->name('series.bulk-destroy');
    Route::resource('series', SeriesController::class);

    // Metadata and Logs
    Route::resource('activities', ActivityController::class)->only(['index', 'show']);
    Route::resource('comments', CommentController::class);
    Route::resource('notes', NoteController::class);
    Route::resource('deletions', DeletionController::class)->only(['index', 'show']);

});

// 🧪 SANDBOX ROUTES (Temporary for Development)
Route::get('/dev/editor', function () {
    return \Inertia\Inertia::render('Technologies/Editor/Sandbox');
})->name('dev.editor');

Route::get('/dev/player/{type}/{slug}', function ($type, $slug) {
    $modelClass = match ($type) {
        'audio' => \App\Models\Audio::class,
        'video' => \App\Models\Video::class,
        default => abort(404, 'Media type not found'),
    };

    $media = $modelClass::where('slug', $slug)->with(['authors', 'versions.publisher'])->firstOrFail();

    return \Inertia\Inertia::render('Technologies/Player/Sandbox', [
        'media' => $media,
        'type' => $type
    ]);
})->name('dev.player');

Route::get('/dev/manuscripter/{manuscript:slug}', [\App\Http\Controllers\ManuscriptController::class, 'sandbox'])->name('dev.manuscripter');

// Reader Technology Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/reader/{type}/{slug}/search', [App\Http\Controllers\ReaderController::class, 'search'])
        ->name('reader.search');
    Route::post('/api/reader/position', [App\Http\Controllers\ReaderController::class, 'savePosition'])
        ->name('reader.save-position');
});

Route::get('/reader/{type}/{slug}/{childId?}', [App\Http\Controllers\ReaderController::class, 'show'])
    ->name('reader.show');

