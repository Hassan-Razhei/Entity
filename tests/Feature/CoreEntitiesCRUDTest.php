<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Audio;
use App\Models\Author;
use App\Models\Book;
use App\Models\Booker;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\ContentNode;
use App\Models\Deletion;
use App\Models\Language;
use App\Models\Manuscript;
use App\Models\Note;
use App\Models\Publisher;
use App\Models\ReadingPosition;
use App\Models\Series;
use App\Models\Shelf;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Models\Version;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoreEntitiesCRUDTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Storage::fake('public');
    }

    // ==========================================
    // 1. BOOK CRUD
    // ==========================================
    #[Test]
    public function full_crud_lifecycle_for_books(): void
    {
        $author = Author::factory()->create(['name' => 'مؤلف الكتاب']);
        $category = Category::factory()->create(['name' => 'الفقه الإسلامي']);
        $tag = Tag::factory()->create(['name' => 'أصول']);
        $publisher = Publisher::factory()->create(['name' => 'دار النشر']);

        // Create
        $file = UploadedFile::fake()->create('book.pdf', 100);
        $cover = UploadedFile::fake()->image('cover.jpg');

        $response = $this->post(route('books.store'), [
            'title' => 'كتاب الأصول التام',
            'author_ids' => [$author->id],
            'categories' => [$category->id],
            'tags' => [$tag->id],
            'publisher_id' => $publisher->id,
            'description' => 'وصف تفصيلي لكتاب الأصول',
            'published_year' => 2024,
            'pages' => 350,
            'file' => $file,
            'cover' => $cover,
        ]);

        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseHas('books', ['title' => 'كتاب الأصول التام']);

        $book = Book::where('title', 'كتاب الأصول التام')->firstOrFail();
        $this->assertTrue($book->authors->contains($author));
        $this->assertTrue($book->categories->contains($category));
        $this->assertTrue($book->tags->contains($tag));

        // Read (Index)
        $indexResponse = $this->get(route('books.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertInertia(fn ($page) => $page
            ->component('Books/Index')
            ->has('books.data')
        );

        // Read (Show)
        $showResponse = $this->get(route('books.show', $book));
        $showResponse->assertStatus(200);
        $showResponse->assertInertia(fn ($page) => $page
            ->component('Books/Show')
            ->has('book')
        );

        // Update
        $updateResponse = $this->put(route('books.update', $book), [
            'title' => 'كتاب الأصول المطور',
            'description' => 'وصف معدل',
        ]);
        $book->refresh();
        $updateResponse->assertRedirect(route('books.show', $book));
        $this->assertEquals('كتاب الأصول المطور', $book->title);

        // Delete
        $deleteResponse = $this->delete(route('books.destroy', $book));
        $deleteResponse->assertRedirect(route('books.index'));
        $this->assertSoftDeleted('books', ['id' => $book->id]);
    }

    // ==========================================
    // 2. MANUSCRIPT CRUD
    // ==========================================
    #[Test]
    public function full_crud_lifecycle_for_manuscripts(): void
    {
        $author = Author::factory()->create(['name' => 'مؤلف المخطوط']);
        $category = Category::factory()->create(['name' => 'المخطوطات النادرة']);

        // Create
        $response = $this->post(route('manuscripts.store'), [
            'title' => 'مخطوطة الدر النضيد',
            'manuscript_century' => '7',
            'manuscript_century_label' => 'القرن السابع الهجري',
            'catalog_number' => 'MS-1049',
            'scribe' => 'علي بن الحسن',
            'author_ids' => [$author->id],
            'categories' => [$category->id],
            'description' => 'مخطوطة فريدة بخط النسخ',
        ]);

        $response->assertRedirect(route('manuscripts.index'));
        $this->assertDatabaseHas('manuscripts', ['title' => 'مخطوطة الدر النضيد', 'catalog_number' => 'MS-1049']);

        $manuscript = Manuscript::where('title', 'مخطوطة الدر النضيد')->firstOrFail();
        $this->assertTrue($manuscript->authors->contains($author));

        // Read (Index & Show)
        $this->get(route('manuscripts.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Manuscripts/Index'));

        $this->get(route('manuscripts.show', $manuscript))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Manuscripts/Show'));

        // Update
        $updateResponse = $this->put(route('manuscripts.update', $manuscript), [
            'title' => 'مخطوطة الدر النضيد - النسخة المصححة',
            'scribe' => 'علي بن الحسن الدمشقي',
        ]);
        $manuscript->refresh();
        $updateResponse->assertRedirect(route('manuscripts.show', $manuscript));
        $this->assertEquals('مخطوطة الدر النضيد - النسخة المصححة', $manuscript->title);

        // Delete
        $this->delete(route('manuscripts.destroy', $manuscript))
            ->assertRedirect(route('manuscripts.index'));
        $this->assertSoftDeleted('manuscripts', ['id' => $manuscript->id]);
    }

    // ==========================================
    // 3. AUDIO CRUD
    // ==========================================
    #[Test]
    public function full_crud_lifecycle_for_audios(): void
    {
        $author = Author::factory()->create(['name' => 'الشيخ الشارح']);
        $file = UploadedFile::fake()->create('lecture.mp3', 100);

        // Create
        $response = $this->post(route('audios.store'), [
            'title' => 'محاضرة في علوم القرآن',
            'author_ids' => [$author->id],
            'file' => $file,
            'description' => 'تسجيل صوتي عالي النقاء',
        ]);

        $response->assertRedirect(route('audios.index'));
        $this->assertDatabaseHas('audios', ['title' => 'محاضرة في علوم القرآن']);

        $audio = Audio::where('title', 'محاضرة في علوم القرآن')->firstOrFail();

        // Read (Index & Show)
        $this->get(route('audios.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Audios/Index'));

        $this->get(route('audios.show', $audio))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Audios/Show'));

        // Update
        $updateResponse = $this->put(route('audios.update', $audio), [
            'title' => 'محاضرة في علوم القرآن (الجزء الأول)',
        ]);
        $audio->refresh();
        $updateResponse->assertRedirect(route('audios.show', $audio));
        $this->assertEquals('محاضرة في علوم القرآن (الجزء الأول)', $audio->title);

        // Delete
        $this->delete(route('audios.destroy', $audio))
            ->assertRedirect(route('audios.index'));
        $this->assertSoftDeleted('audios', ['id' => $audio->id]);
    }

    // ==========================================
    // 4. VIDEO CRUD
    // ==========================================
    #[Test]
    public function full_crud_lifecycle_for_videos(): void
    {
        $author = Author::factory()->create(['name' => 'المحاضر المرئي']);
        $file = UploadedFile::fake()->create('clip.mp4', 100);

        // Create
        $response = $this->post(route('videos.store'), [
            'title' => 'درس مرئي تطبيقي',
            'author_ids' => [$author->id],
            'file' => $file,
            'description' => 'مرئيات تعليمية',
        ]);

        $response->assertRedirect(route('videos.index'));
        $this->assertDatabaseHas('videos', ['title' => 'درس مرئي تطبيقي']);

        $video = Video::where('title', 'درس مرئي تطبيقي')->firstOrFail();

        // Read (Index & Show)
        $this->get(route('videos.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Videos/Index'));

        $this->get(route('videos.show', $video))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Videos/Show'));

        // Update
        $updateResponse = $this->put(route('videos.update', $video), [
            'title' => 'درس مرئي تطبيقي محدث',
        ]);
        $video->refresh();
        $updateResponse->assertRedirect(route('videos.show', $video));
        $this->assertEquals('درس مرئي تطبيقي محدث', $video->title);

        // Delete
        $this->delete(route('videos.destroy', $video))
            ->assertRedirect(route('videos.index'));
        $this->assertSoftDeleted('videos', ['id' => $video->id]);
    }

    // ==========================================
    // 5. TAXONOMIES & SUPPORTING ENTITIES CRUD
    // ==========================================
    #[Test]
    public function crud_lifecycle_for_supporting_entities(): void
    {
        // 1. Author
        $authorRes = $this->post(route('authors.store'), [
            'name' => 'العلامة ابن خلدون',
            'bio' => 'مؤرخ وفيلسوف مغربي أندلسي',
        ]);
        $authorRes->assertRedirect(route('authors.index'));
        $author = Author::where('name', 'العلامة ابن خلدون')->firstOrFail();

        $this->put(route('authors.update', $author), ['name' => 'عبد الرحمن بن خلدون'])
            ->assertRedirect(route('authors.show', $author));
        $this->delete(route('authors.destroy', $author))
            ->assertRedirect(route('authors.index'));
        $this->assertSoftDeleted('authors', ['id' => $author->id]);

        // 2. Category
        $catRes = $this->post(route('categories.store'), ['name' => 'التاريخ والحضارة']);
        $catRes->assertRedirect(route('categories.index'));
        $category = Category::where('name', 'التاريخ والحضارة')->firstOrFail();

        $this->put(route('categories.update', $category), ['name' => 'التاريخ الإسلامي'])
            ->assertRedirect(route('categories.show', $category));
        $this->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        // 3. Tag
        $tagRes = $this->post(route('tags.store'), ['name' => 'وسم-تجريبي']);
        $tagRes->assertRedirect(route('tags.index'));
        $tag = Tag::where('name', 'وسم-تجريبي')->firstOrFail();

        $this->put(route('tags.update', $tag), ['name' => 'وسم-محدث'])
            ->assertRedirect(route('tags.show', $tag));
        $this->delete(route('tags.destroy', $tag))
            ->assertRedirect(route('tags.index'));

        // 4. Publisher
        $pubRes = $this->post(route('publishers.store'), [
            'name' => 'دار الرسالة العالمية',
            'city' => 'بيروت',
            'country' => 'لبنان',
        ]);
        $pubRes->assertRedirect(route('publishers.index'));
        $publisher = Publisher::where('name', 'دار الرسالة العالمية')->firstOrFail();

        $this->put(route('publishers.update', $publisher), ['name' => 'دار الرسالة ناشرون'])
            ->assertRedirect(route('publishers.show', $publisher));
        $this->delete(route('publishers.destroy', $publisher))
            ->assertRedirect(route('publishers.index'));

        // 5. Topic
        $topicRes = $this->post(route('topics.store'), ['name' => 'فقه العبادات']);
        $topicRes->assertRedirect(route('topics.index'));
        $topic = Topic::where('name', 'فقه العبادات')->firstOrFail();

        $this->put(route('topics.update', $topic), ['name' => 'فقه المعاملات'])
            ->assertRedirect(route('topics.show', $topic));
        $this->delete(route('topics.destroy', $topic))
            ->assertRedirect(route('topics.index'));

        // 6. Series
        $seriesRes = $this->post(route('series.store'), ['title' => 'سلسلة أعلام المسلمين']);
        $seriesRes->assertRedirect(route('series.index'));
        $series = Series::where('title', 'سلسلة أعلام المسلمين')->firstOrFail();

        $this->put(route('series.update', $series), ['title' => 'موسوعة أعلام المسلمين'])
            ->assertRedirect(route('series.show', $series));
        $this->delete(route('series.destroy', $series))
            ->assertRedirect(route('series.index'));

        // 7. Shelf
        $shelfRes = $this->post(route('shelves.store'), [
            'location_code' => 'SH-A1',
            'capacity' => 100,
        ]);
        $shelfRes->assertRedirect(route('shelves.index'));
        $shelf = Shelf::where('location_code', 'SH-A1')->firstOrFail();

        $this->put(route('shelves.update', $shelf), ['capacity' => 150])
            ->assertRedirect(route('shelves.index'));
        $this->delete(route('shelves.destroy', $shelf))
            ->assertRedirect(route('shelves.index'));

        // 8. Collection
        $collectionRes = $this->post(route('collections.store'), [
            'name' => 'مجموعة أمهات الكتب',
            'description' => 'مجموعة متميزة للأبحاث',
            'is_public' => true,
        ]);
        $collectionRes->assertRedirect(route('collections.index'));
        $collection = Collection::where('name', 'مجموعة أمهات الكتب')->firstOrFail();

        $this->put(route('collections.update', $collection), ['name' => 'مجموعة أمهات الكتب النفيسة'])
            ->assertRedirect(route('collections.index'));
        $this->delete(route('collections.destroy', $collection))
            ->assertRedirect(route('collections.index'));
    }

    // ==========================================
    // 6. BOOKERS (المساهمون) CRUD
    // ==========================================
    #[Test]
    public function full_crud_lifecycle_for_bookers(): void
    {
        // Create
        $response = $this->post(route('bookers.store'), [
            'name' => 'الأستاذ المحقق محمد',
        ]);
        $response->assertRedirect(route('bookers.index'));
        $this->assertDatabaseHas('bookers', ['name' => 'الأستاذ المحقق محمد']);

        $booker = Booker::where('name', 'الأستاذ المحقق محمد')->firstOrFail();

        // Read (Index, Show, Edit)
        $this->get(route('bookers.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Bookers/Index'));

        $this->get(route('bookers.show', $booker))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Bookers/Show'));

        $this->get(route('bookers.edit', $booker))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Bookers/Edit'));

        // Update
        $this->put(route('bookers.update', $booker), [
            'name' => 'الأستاذ المحقق محمد المحدث',
        ])->assertRedirect(route('bookers.index'));

        $booker->refresh();
        $this->assertEquals('الأستاذ المحقق محمد المحدث', $booker->name);

        // Delete
        $this->delete(route('bookers.destroy', $booker))
            ->assertRedirect(route('bookers.index'));
        $this->assertSoftDeleted('bookers', ['id' => $booker->id]);

        // Bulk Destroy
        $b1 = Booker::create(['name' => 'مساهم 1', 'slug' => 'contributor-1']);
        $b2 = Booker::create(['name' => 'مساهم 2', 'slug' => 'contributor-2']);
        $this->post(route('bookers.bulk-destroy'), [
            'ids' => [$b1->id, $b2->id],
        ])->assertSessionHasNoErrors();
        $this->assertSoftDeleted('bookers', ['id' => $b1->id]);
        $this->assertSoftDeleted('bookers', ['id' => $b2->id]);
    }

    // ==========================================
    // 7. LANGUAGES (اللغات) CRUD
    // ==========================================
    #[Test]
    public function full_crud_lifecycle_for_languages(): void
    {
        // Create
        $response = $this->post(route('languages.store'), [
            'name' => 'اللغة العربية الفصحى',
            'code' => 'ar',
        ]);
        $response->assertRedirect(route('languages.index'));
        $this->assertDatabaseHas('languages', ['name' => 'اللغة العربية الفصحى', 'code' => 'ar']);

        $language = Language::where('code', 'ar')->firstOrFail();

        // Read (Index, Show, Edit)
        $this->get(route('languages.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Languages/Index'));

        $this->get(route('languages.show', $language))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Languages/Show'));

        $this->get(route('languages.edit', $language))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Languages/Edit'));

        // Update
        $this->put(route('languages.update', $language), [
            'name' => 'العربية المعاصرة',
            'code' => 'ara',
        ])->assertRedirect(route('languages.index'));

        $language->refresh();
        $this->assertEquals('العربية المعاصرة', $language->name);

        // Delete
        $this->delete(route('languages.destroy', $language))
            ->assertRedirect(route('languages.index'));
        $this->assertDatabaseMissing('languages', ['id' => $language->id]);
    }

    // ==========================================
    // 8. COMMENTS & NOTES CRUD
    // ==========================================
    #[Test]
    public function full_crud_lifecycle_for_comments_and_notes(): void
    {
        $book = Book::factory()->create(['title' => 'كتاب للاختبار الميتاداتا']);

        // --- Comments CRUD ---
        $commentRes = $this->post(route('comments.store'), [
            'content' => 'تعليق قيم ومفيد حول محتوى الكتاب',
            'entity_id' => $book->id,
            'entity_type' => 'book',
        ]);
        $commentRes->assertRedirect(route('comments.index'));
        $this->assertDatabaseHas('comments', ['content' => 'تعليق قيم ومفيد حول محتوى الكتاب']);

        $comment = Comment::where('entity_id', $book->id)->firstOrFail();

        $this->get(route('comments.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Comments/Index'));

        $this->get(route('comments.show', $comment))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Comments/Show'));

        $this->get(route('comments.edit', $comment))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Comments/Edit'));

        $this->put(route('comments.update', $comment), [
            'content' => 'تعليق قيم ومفيد ومحدث',
        ])->assertRedirect(route('comments.show', $comment));

        $comment->refresh();
        $this->assertEquals('تعليق قيم ومفيد ومحدث', $comment->content);

        $this->delete(route('comments.destroy', $comment))
            ->assertRedirect(route('comments.index'));
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);

        // --- Notes CRUD ---
        $noteRes = $this->post(route('notes.store'), [
            'content' => 'ملاحظة تدقيقية للباب الأول',
            'entity_id' => $book->id,
            'entity_type' => 'book',
        ]);
        $noteRes->assertRedirect(route('notes.index'));
        $this->assertDatabaseHas('notes', ['content' => 'ملاحظة تدقيقية للباب الأول']);

        $note = Note::where('entity_id', $book->id)->firstOrFail();

        $this->get(route('notes.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Notes/Index'));

        $this->get(route('notes.show', $note))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Notes/Show'));

        $this->get(route('notes.edit', $note))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Notes/Edit'));

        $this->put(route('notes.update', $note), [
            'content' => 'ملاحظة تدقيقية للباب الأول - تمت المراجعة',
        ])->assertRedirect(route('notes.show', $note));

        $note->refresh();
        $this->assertEquals('ملاحظة تدقيقية للباب الأول - تمت المراجعة', $note->content);

        $this->delete(route('notes.destroy', $note))
            ->assertRedirect(route('notes.index'));
        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    // ==========================================
    // 9. ACTIVITIES & DELETIONS (Audit Logs)
    // ==========================================
    #[Test]
    public function audit_logs_lifecycle_for_activities_and_deletions(): void
    {
        $book = Book::factory()->create();

        // 1. Activity Log
        $activity = Activity::create([
            'user_id' => $this->user->id,
            'entity_type' => 'book',
            'entity_id' => $book->id,
            'activity_type' => 'created',
            'description' => 'قام المستخدم بإضافة كتاب جديد إلى النظام',
        ]);

        $this->get(route('activities.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Activities/Index'));

        $this->get(route('activities.show', $activity))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Activities/Show'));

        // 2. Deletion Log
        $deletion = Deletion::create([
            'user_id' => $this->user->id,
            'entity_type' => 'book',
            'entity_id' => $book->id,
            'reason' => 'تم الحذف لإعادة الفهرسة',
        ]);

        $this->get(route('deletions.index'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Deletions/Index'));

        $this->get(route('deletions.show', $deletion))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Deletions/Show'));
    }

    // ==========================================
    // 10. SEGMENTS & CONTENT NODES API CRUD
    // ==========================================
    #[Test]
    public function crud_lifecycle_for_content_nodes_and_segments(): void
    {
        $audio = Audio::factory()->create([
            'title' => 'تسجيل محاضرة صوتية',
            'duration' => 3600,
        ]);

        // 1. Create Segment via API
        $createRes = $this->postJson(route('api.segments.store'), [
            'entity_id' => $audio->id,
            'entity_type' => 'audio',
            'title' => 'مقدمة المحاضرة',
            'start_time' => 0,
            'end_time' => 120,
        ]);
        $createRes->assertStatus(201);
        $segmentId = $createRes->json('segment.id');
        $this->assertNotNull($segmentId);

        // 2. Update Segment via API
        $updateRes = $this->putJson(route('api.segments.update', $segmentId), [
            'entity_id' => $audio->id,
            'entity_type' => 'audio',
            'title' => 'مقدمة المحاضرة - منقحة',
            'start_time' => 5,
        ]);
        $updateRes->assertStatus(200);

        // 3. Delete Segment via API
        $deleteRes = $this->deleteJson(route('api.segments.destroy', $segmentId), [
            'entity_id' => $audio->id,
            'entity_type' => 'audio',
        ]);
        $deleteRes->assertStatus(200);

        // 4. Create Studio Node
        $nodeRes = $this->postJson(route('studio.nodes.store', [
            'type' => 'audio',
            'slug' => $audio->slug,
        ]), [
            'type' => 'segment',
            'title' => 'المقطع الرئيس الأول',
            'time' => 60,
        ]);
        $nodeRes->assertStatus(200)
            ->assertJsonStructure(['message', 'node', 'redirect']);
    }

    // ==========================================
    // 11. VERSIONS & READING POSITIONS
    // ==========================================
    #[Test]
    public function lifecycle_for_versions_and_reading_positions(): void
    {
        $book = Book::factory()->create(['title' => 'كتاب النسخ والمواضع']);

        // 1. Version lifecycle
        $version = Version::create([
            'versionable_id' => $book->id,
            'versionable_type' => $book->getMorphClass(),
            'title' => 'الطبعة الأولى المحققة',
            'format' => 'pdf',
            'file_size' => 1048576,
            'pages' => 250,
            'published_year' => 2023,
            'edition_number' => 1,
        ]);

        $this->assertDatabaseHas('versions', ['title' => 'الطبعة الأولى المحققة']);
        $this->assertEquals($book->id, $version->versionable->id);

        $version->update(['title' => 'الطبعة الثانية المزيدة']);
        $this->assertEquals('الطبعة الثانية المزيدة', $version->fresh()->title);

        $version->delete();
        $this->assertSoftDeleted('versions', ['id' => $version->id]);

        // 2. Reading Position lifecycle
        $savePosRes = $this->postJson(route('reader.save-position'), [
            'entity_id' => $book->id,
            'entity_type' => 'book',
            'node_slug' => 'chapter-1',
            'scroll_offset' => 1500,
            'timestamp' => 300,
        ]);
        $savePosRes->assertStatus(200);
        $this->assertDatabaseHas('reading_positions', [
            'user_id' => $this->user->id,
            'entity_id' => $book->id,
            'node_slug' => 'chapter-1',
        ]);
    }
}
