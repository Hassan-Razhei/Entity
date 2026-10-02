<?php

namespace Tests\Feature\Studio;

use App\Models\Audio;
use App\Models\Book;
use App\Models\ContentNode;
use App\Models\Manuscript;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComprehensiveStudioSaveAndReloadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    // ========================================================
    // 1. AUDIO: SPECIFIC SEGMENT SAVE & RELOAD
    // ========================================================
    #[Test]
    public function audio_specific_segment_save_and_reload(): void
    {
        $audio = Audio::factory()->create(['title' => 'محاضرة التفسير', 'duration' => 1800]);

        $segment = $audio->nodes()->create([
            'slug' => 'audio-seg-1',
            'type' => 'segment',
            'title' => 'المقطع الصوتي الأول',
            'order' => 1,
            'metadata' => ['start_time' => 10.0, 'end_time' => 70.0],
            'content_html' => '<p>المحتوى الأصلي للصوت</p>',
            'plain_text' => 'المحتوى الأصلي للصوت',
        ]);

        // 1. Initial Load / Reload
        $loadRes = $this->get("/studio/audio/{$audio->slug}/{$segment->id}");
        $loadRes->assertStatus(200);
        $props = $loadRes->viewData('page')['props'];
        $this->assertEquals('<p>المحتوى الأصلي للصوت</p>', $props['editorContent']);
        $this->assertEquals($segment->id, $props['contentNode']['id']);
        $this->assertFalse($props['isFullView']);

        // 2. Save modifications
        $newContent = '<p>المحتوى الصوتي المحدث بعد الحفظ والتعديل</p>';
        $saveRes = $this->postJson("/studio/audio/{$audio->slug}/{$segment->id}/save", [
            'child_id' => $segment->id,
            'title' => 'المقطع الصوتي الأول (منقح)',
            'content' => $newContent,
            'html_content' => $newContent,
            'plain_text' => strip_tags($newContent),
        ]);
        $saveRes->assertStatus(200)->assertJson(['message' => 'تم الحفظ بنجاح']);

        // 3. Reload page and assert new content persists
        $reloadRes = $this->get("/studio/audio/{$audio->slug}/{$segment->id}");
        $reloadRes->assertStatus(200);
        $reloadedProps = $reloadRes->viewData('page')['props'];
        $this->assertEquals($newContent, $reloadedProps['editorContent']);
        $this->assertEquals('المقطع الصوتي الأول (منقح)', $reloadedProps['contentNode']['title']);
        $this->assertEquals($newContent, $reloadedProps['contentNode']['content']);
        $this->assertEquals($newContent, $reloadedProps['contentNode']['html_content']);
    }

    // ========================================================
    // 2. VIDEO: SPECIFIC SEGMENT SAVE & RELOAD
    // ========================================================
    #[Test]
    public function video_specific_segment_save_and_reload(): void
    {
        $video = Video::factory()->create(['title' => 'الدرس المرئي الأول', 'duration' => 2400]);

        $segment = $video->nodes()->create([
            'slug' => 'video-seg-1',
            'type' => 'segment',
            'title' => 'المشهد الأول',
            'order' => 1,
            'metadata' => ['start_time' => 0.0, 'end_time' => 120.0],
            'content_html' => '<p>تفريغ المشهد المرئي الأول</p>',
            'plain_text' => 'تفريغ المشهد المرئي الأول',
        ]);

        // Save modifications
        $updatedHtml = '<p>تفريغ المشهد المرئي الأول بعد التنقيح الكامل</p>';
        $saveRes = $this->postJson("/studio/video/{$video->slug}/{$segment->id}/save", [
            'child_id' => $segment->id,
            'title' => 'المشهد الأول - معدل',
            'content' => $updatedHtml,
            'html_content' => $updatedHtml,
            'plain_text' => strip_tags($updatedHtml),
        ]);
        $saveRes->assertStatus(200);

        // Reload page and assert
        $reloadRes = $this->get("/studio/video/{$video->slug}/{$segment->id}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];
        $this->assertEquals($updatedHtml, $props['editorContent']);
        $this->assertEquals('المشهد الأول - معدل', $props['contentNode']['title']);
        $this->assertEquals($updatedHtml, $props['contentNode']['content']);
    }

    // ========================================================
    // 3. BOOK: SPECIFIC NODE SAVE & RELOAD
    // ========================================================
    #[Test]
    public function book_specific_node_save_and_reload(): void
    {
        $book = Book::factory()->create(['title' => 'كتاب الأصول']);

        $chapter = $book->nodes()->create([
            'slug' => 'book-chap-1',
            'type' => 'chapter',
            'title' => 'باب الإجماع',
            'order' => 1,
            'content_html' => '<p>نص باب الإجماع الأصلي</p>',
            'plain_text' => 'نص باب الإجماع الأصلي',
        ]);

        // Save modifications
        $newBookText = '<p>نص باب الإجماع بعد التحقيق العلمي</p>';
        $saveRes = $this->postJson("/studio/book/{$book->slug}/{$chapter->id}/save", [
            'child_id' => $chapter->id,
            'title' => 'باب الإجماع وأدلته',
            'content' => $newBookText,
            'html_content' => $newBookText,
            'plain_text' => strip_tags($newBookText),
        ]);
        $saveRes->assertStatus(200);

        // Reload page
        $reloadRes = $this->get("/studio/book/{$book->slug}/{$chapter->id}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];

        $this->assertEquals('باب الإجماع وأدلته', $props['contentNode']['title']);
        $this->assertEquals($newBookText, $props['contentNode']['content']);
        $this->assertEquals($newBookText, $props['contentNode']['html_content']);
        $this->assertStringContainsString('نص باب الإجماع بعد التحقيق العلمي', $props['editorContent']);
    }

    // ========================================================
    // 4. MANUSCRIPT: SPECIFIC NODE SAVE & RELOAD
    // ========================================================
    #[Test]
    public function manuscript_specific_node_save_and_reload(): void
    {
        $manuscript = Manuscript::factory()->create(['title' => 'مخطوطة نادرة']);

        $folio = $manuscript->nodes()->create([
            'slug' => 'folio-1a',
            'type' => 'folio',
            'title' => 'اللوحة 1-أ',
            'order' => 1,
            'content_html' => '<p>نص اللوحة الأولى من المخطوط</p>',
            'plain_text' => 'نص اللوحة الأولى من المخطوط',
        ]);

        $newText = '<p>نص اللوحة الأولى بعد قراءة خط النسخ بدقة</p>';
        $saveRes = $this->postJson("/studio/manuscript/{$manuscript->slug}/{$folio->id}/save", [
            'child_id' => $folio->id,
            'title' => 'اللوحة 1-أ (محققة)',
            'content' => $newText,
            'html_content' => $newText,
            'plain_text' => strip_tags($newText),
        ]);
        $saveRes->assertStatus(200);

        // Reload page
        $reloadRes = $this->get("/studio/manuscript/{$manuscript->slug}/{$folio->id}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];

        $this->assertEquals('اللوحة 1-أ (محققة)', $props['contentNode']['title']);
        $this->assertEquals($newText, $props['contentNode']['content']);
        $this->assertStringContainsString('نص اللوحة الأولى بعد قراءة خط النسخ بدقة', $props['editorContent']);
    }

    // ========================================================
    // 5. AUDIO: FULL VIEW SMART SAVE & RELOAD
    // ========================================================
    #[Test]
    public function audio_full_view_smart_save_and_reload(): void
    {
        $audio = Audio::factory()->create(['title' => 'صوت كامل المحتوى', 'duration' => 3600]);

        $seg1 = $audio->nodes()->create([
            'slug' => 'full-seg-1',
            'type' => 'segment',
            'title' => 'مقطع 1',
            'order' => 1,
            'metadata' => ['start_time' => 0.0],
            'content_html' => '<p>قديم 1</p>',
        ]);

        $seg2 = $audio->nodes()->create([
            'slug' => 'full-seg-2',
            'type' => 'segment',
            'title' => 'مقطع 2',
            'order' => 2,
            'metadata' => ['start_time' => 100.0],
            'content_html' => '<p>قديم 2</p>',
        ]);

        // Save aggregated content
        $aggregatedHtml =
            "<h4 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$seg1->id}\">مقطع 1</h4>\n" .
            "<p>نص مقطع 1 بعد الحفظ الذكي</p>\n" .
            "<h4 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$seg2->id}\">مقطع 2</h4>\n" .
            "<p>نص مقطع 2 بعد الحفظ الذكي</p>";

        $saveRes = $this->postJson("/studio/audio/{$audio->slug}/full/save", [
            'child_id' => 'full',
            'content' => $aggregatedHtml,
            'html_content' => $aggregatedHtml,
        ]);
        $saveRes->assertStatus(200)->assertJson(['message' => 'تم الحفظ بنجاح']);

        // Reload Full View
        $reloadRes = $this->get("/studio/audio/{$audio->slug}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];
        $this->assertTrue($props['isFullView']);
        $this->assertStringContainsString('نص مقطع 1 بعد الحفظ الذكي', $props['editorContent']);
        $this->assertStringContainsString('نص مقطع 2 بعد الحفظ الذكي', $props['editorContent']);

        // Reload specific segments to ensure they were updated in database
        $seg1->refresh();
        $seg2->refresh();
        $this->assertEquals('<p>نص مقطع 1 بعد الحفظ الذكي</p>', $seg1->content_html);
        $this->assertEquals('<p>نص مقطع 2 بعد الحفظ الذكي</p>', $seg2->content_html);
    }

    // ========================================================
    // 6. VIDEO: FULL VIEW SMART SAVE & RELOAD
    // ========================================================
    #[Test]
    public function video_full_view_smart_save_and_reload(): void
    {
        $video = Video::factory()->create(['title' => 'فيديو كامل المحتوى', 'duration' => 2000]);

        $seg1 = $video->nodes()->create([
            'slug' => 'v-seg-1',
            'type' => 'segment',
            'title' => 'مشهد 1',
            'order' => 1,
            'metadata' => ['start_time' => 0.0],
            'content_html' => '<p>قديم مرئي 1</p>',
        ]);

        $seg2 = $video->nodes()->create([
            'slug' => 'v-seg-2',
            'type' => 'segment',
            'title' => 'مشهد 2',
            'order' => 2,
            'metadata' => ['start_time' => 200.0],
            'content_html' => '<p>قديم مرئي 2</p>',
        ]);

        $aggregatedHtml =
            "<h4 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$seg1->id}\">مشهد 1</h4>\n" .
            "<p>نص مشهد 1 المحدث</p>\n" .
            "<h4 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$seg2->id}\">مشهد 2</h4>\n" .
            "<p>نص مشهد 2 المحدث</p>";

        $saveRes = $this->postJson("/studio/video/{$video->slug}/full/save", [
            'child_id' => 'full',
            'content' => $aggregatedHtml,
            'html_content' => $aggregatedHtml,
        ]);
        $saveRes->assertStatus(200);

        // Reload Full View
        $reloadRes = $this->get("/studio/video/{$video->slug}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];
        $this->assertTrue($props['isFullView']);
        $this->assertStringContainsString('نص مشهد 1 المحدث', $props['editorContent']);
        $this->assertStringContainsString('نص مشهد 2 المحدث', $props['editorContent']);
    }

    // ========================================================
    // 7. BOOK & MANUSCRIPT: FULL VIEW SMART SAVE & RELOAD
    // ========================================================
    #[Test]
    public function book_full_view_smart_save_and_reload(): void
    {
        $book = Book::factory()->create(['title' => 'كتاب فقه المعاملات']);

        $chap1 = $book->nodes()->create([
            'slug' => 'chap-buy',
            'type' => 'chapter',
            'title' => 'كتاب البيوع',
            'order' => 1,
            'content_html' => '<p>مسائل البيوع</p>',
        ]);

        $chap2 = $book->nodes()->create([
            'slug' => 'chap-rent',
            'type' => 'chapter',
            'title' => 'كتاب الإجارة',
            'order' => 2,
            'content_html' => '<p>مسائل الإجارة</p>',
        ]);

        $aggregatedHtml =
            "<h2 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$chap1->id}\">كتاب البيوع</h2>\n" .
            "<p>مسائل البيوع المنقحة</p>\n" .
            "<h2 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$chap2->id}\">كتاب الإجارة</h2>\n" .
            "<p>مسائل الإجارة المنقحة</p>";

        $saveRes = $this->postJson("/studio/book/{$book->slug}/full/save", [
            'child_id' => 'full',
            'content' => $aggregatedHtml,
            'html_content' => $aggregatedHtml,
        ]);
        $saveRes->assertStatus(200);

        // Reload Full View
        $reloadRes = $this->get("/studio/book/{$book->slug}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];
        $this->assertTrue($props['isFullView']);
        $this->assertStringContainsString('مسائل البيوع المنقحة', $props['editorContent']);
        $this->assertStringContainsString('مسائل الإجارة المنقحة', $props['editorContent']);

        $chap1->refresh();
        $chap2->refresh();
        $this->assertEquals('<p>مسائل البيوع المنقحة</p>', $chap1->content_html);
        $this->assertEquals('<p>مسائل الإجارة المنقحة</p>', $chap2->content_html);
    }

    // ========================================================
    // 8. STUDIO RESUME: RELOAD TO LAST ACTIVE SESSION
    // ========================================================
    #[Test]
    public function studio_resume_redirects_to_exact_last_saved_or_opened_session(): void
    {
        $audio = Audio::factory()->create(['title' => 'صوت للاستئناف', 'duration' => 1200]);
        $seg = $audio->nodes()->create([
            'slug' => 'resume-seg',
            'type' => 'segment',
            'title' => 'مقطع الاستئناف',
            'order' => 1,
            'content_html' => '<p>محتوى</p>',
        ]);

        // 1. Visit specific segment -> session recorded
        $this->get("/studio/audio/{$audio->slug}/{$seg->id}")->assertStatus(200);
        $this->user->refresh();
        $this->assertEquals('audio', $this->user->last_studio_type);
        $this->assertEquals($audio->slug, $this->user->last_studio_slug);
        $this->assertEquals($seg->id, $this->user->last_studio_child_id);

        // 2. Call /studio/resume -> redirects to specific segment
        $resumeRes = $this->get(route('studio.resume'));
        $resumeRes->assertRedirect(route('studio.show', [
            'type' => 'audio',
            'slug' => $audio->slug,
            'childId' => $seg->id,
        ]));

        // 3. Visit Full View -> child_id is null
        $this->get("/studio/audio/{$audio->slug}")->assertStatus(200);
        $this->user->refresh();
        $this->assertNull($this->user->last_studio_child_id);

        // 4. Resume redirects to Full View
        $this->get(route('studio.resume'))->assertRedirect(route('studio.show', [
            'type' => 'audio',
            'slug' => $audio->slug,
            'childId' => null,
        ]));
    }

    // ========================================================
    // 9. CONSECUTIVE EDITING & ISOLATION (NO CROSS-CONTAMINATION)
    // ========================================================
    #[Test]
    public function saving_consecutive_segments_maintains_isolation_on_reload(): void
    {
        $audio = Audio::factory()->create(['title' => 'سلسلة مقاطع', 'duration' => 2000]);

        $segA = $audio->nodes()->create([
            'slug' => 'seg-a',
            'type' => 'segment',
            'title' => 'المقطع أ',
            'order' => 1,
            'content_html' => '<p>النص أ</p>',
        ]);

        $segB = $audio->nodes()->create([
            'slug' => 'seg-b',
            'type' => 'segment',
            'title' => 'المقطع ب',
            'order' => 2,
            'content_html' => '<p>النص ب</p>',
        ]);

        // Save Segment A
        $this->postJson("/studio/audio/{$audio->slug}/{$segA->id}/save", [
            'child_id' => $segA->id,
            'title' => 'المقطع أ المطور',
            'content' => '<p>النص أ المطور والفريد</p>',
            'html_content' => '<p>النص أ المطور والفريد</p>',
        ])->assertStatus(200);

        // Save Segment B
        $this->postJson("/studio/audio/{$audio->slug}/{$segB->id}/save", [
            'child_id' => $segB->id,
            'title' => 'المقطع ب المطور',
            'content' => '<p>النص ب المطور والفريد</p>',
            'html_content' => '<p>النص ب المطور والفريد</p>',
        ])->assertStatus(200);

        // Reload Segment A -> check no content from Segment B
        $resA = $this->get("/studio/audio/{$audio->slug}/{$segA->id}");
        $resA->assertStatus(200);
        $propsA = $resA->viewData('page')['props'];
        $this->assertEquals('<p>النص أ المطور والفريد</p>', $propsA['editorContent']);
        $this->assertStringNotContainsString('النص ب', $propsA['editorContent']);

        // Reload Segment B -> check no content from Segment A
        $resB = $this->get("/studio/audio/{$audio->slug}/{$segB->id}");
        $resB->assertStatus(200);
        $propsB = $resB->viewData('page')['props'];
        $this->assertEquals('<p>النص ب المطور والفريد</p>', $propsB['editorContent']);
        $this->assertStringNotContainsString('النص أ', $propsB['editorContent']);
    }

    // ========================================================
    // 10. PAYLOAD RICHNESS: HTML & JSON PRESERVATION
    // ========================================================
    #[Test]
    public function saving_with_json_and_html_payload_preserves_both_on_reload(): void
    {
        $video = Video::factory()->create(['title' => 'فيديو بالبنية الغنية', 'duration' => 1500]);

        $seg = $video->nodes()->create([
            'slug' => 'rich-seg',
            'type' => 'segment',
            'title' => 'مقطع بنية غنية',
            'order' => 1,
            'content_html' => '<p>أولي</p>',
        ]);

        $richHtml = '<p>فقرة أولى</p><blockquote>اقتباس علمي</blockquote>';
        $jsonBlocks = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'فقرة أولى']]],
                ['type' => 'blockquote', 'content' => [['type' => 'text', 'text' => 'اقتباس علمي']]],
            ]
        ];

        $saveRes = $this->postJson("/studio/video/{$video->slug}/{$seg->id}/save", [
            'child_id' => $seg->id,
            'title' => 'مقطع بنية غنية',
            'content' => $richHtml,
            'html_content' => $richHtml,
            'json_content' => $jsonBlocks,
            'plain_text' => 'فقرة أولى اقتباس علمي',
        ]);
        $saveRes->assertStatus(200);

        $seg->refresh();
        $this->assertEquals($richHtml, $seg->content_html);
        $this->assertEquals($jsonBlocks, $seg->content_json);

        // Reload and verify
        $reloadRes = $this->get("/studio/video/{$video->slug}/{$seg->id}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];
        $this->assertEquals($richHtml, $props['editorContent']);
        $this->assertEquals($richHtml, $props['contentNode']['html_content']);
    }

    // ========================================================
    // 11. MANUSCRIPT: FULL VIEW SMART SAVE & RELOAD
    // ========================================================
    #[Test]
    public function manuscript_full_view_smart_save_and_reload(): void
    {
        $manuscript = Manuscript::factory()->create(['title' => 'مخطوطة الفنون']);

        $f1 = $manuscript->nodes()->create([
            'slug' => 'folio-1',
            'type' => 'folio',
            'title' => 'ورقة 1',
            'order' => 1,
            'content_html' => '<p>قديم 1</p>',
        ]);

        $f2 = $manuscript->nodes()->create([
            'slug' => 'folio-2',
            'type' => 'folio',
            'title' => 'ورقة 2',
            'order' => 2,
            'content_html' => '<p>قديم 2</p>',
        ]);

        $aggregatedHtml =
            "<h2 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$f1->id}\">ورقة 1</h2>\n" .
            "<p>نص ورقة 1 المنقحة</p>\n" .
            "<h2 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$f2->id}\">ورقة 2</h2>\n" .
            "<p>نص ورقة 2 المنقحة</p>";

        $saveRes = $this->postJson("/studio/manuscript/{$manuscript->slug}/full/save", [
            'child_id' => 'full',
            'content' => $aggregatedHtml,
            'html_content' => $aggregatedHtml,
        ]);
        $saveRes->assertStatus(200);

        // Reload Full View
        $reloadRes = $this->get("/studio/manuscript/{$manuscript->slug}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];
        $this->assertTrue($props['isFullView']);
        $this->assertStringContainsString('نص ورقة 1 المنقحة', $props['editorContent']);
        $this->assertStringContainsString('نص ورقة 2 المنقحة', $props['editorContent']);

        $f1->refresh();
        $f2->refresh();
        $this->assertEquals('<p>نص ورقة 1 المنقحة</p>', $f1->content_html);
        $this->assertEquals('<p>نص ورقة 2 المنقحة</p>', $f2->content_html);
    }

    // ========================================================
    // 12. VERSION RESTORATION & RELOAD
    // ========================================================
    #[Test]
    public function restore_version_and_subsequent_reload_reflects_restored_content(): void
    {
        $book = Book::factory()->create(['title' => 'كتاب استرجاع النسخ']);

        $node = $book->nodes()->create([
            'slug' => 'versioned-node',
            'type' => 'chapter',
            'title' => 'الفصل المؤرخ',
            'order' => 1,
            'content_html' => '<p>النص الأصلي الأولي</p>',
            'plain_text' => 'النص الأصلي الأولي',
        ]);

        // 1. Create a version snapshot
        $node->createVersion('النسخة الأصلية المحفوظة');
        $this->assertNotEmpty($node->fresh()->versions);

        // 2. User edits and saves new content
        $editedHtml = '<p>النص المعدل الحاضر الذي نريد التراجع عنه</p>';
        $this->postJson("/studio/book/{$book->slug}/{$node->id}/save", [
            'child_id' => $node->id,
            'title' => 'الفصل المؤرخ',
            'content' => $editedHtml,
            'html_content' => $editedHtml,
        ])->assertStatus(200);

        // Verify edited content was saved
        $node->refresh();
        $this->assertEquals($editedHtml, $node->content_html);

        // 3. Restore version index 0
        $restoreRes = $this->postJson("/studio/book/{$book->slug}/{$node->id}/restore/0");
        $restoreRes->assertStatus(200)
            ->assertJson(['message' => 'تم استرجاع النسخة بنجاح']);

        // 4. Reload page and assert restored content is displayed
        $node->refresh();
        $this->assertEquals('<p>النص الأصلي الأولي</p>', $node->content_html);

        $reloadRes = $this->get("/studio/book/{$book->slug}/{$node->id}");
        $reloadRes->assertStatus(200);
        $props = $reloadRes->viewData('page')['props'];
        $this->assertEquals('<p>النص الأصلي الأولي</p>', $props['contentNode']['content']);
        $this->assertStringContainsString('النص الأصلي الأولي', $props['editorContent']);
    }
}
