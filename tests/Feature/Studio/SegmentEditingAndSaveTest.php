<?php

namespace Tests\Feature\Studio;

use App\Models\Audio;
use App\Models\ContentNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SegmentEditingAndSaveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function specific_segment_studio_page_provides_content_node_with_content(): void
    {
        $user = User::factory()->create();
        $audio = Audio::firstOrCreate(
            ['slug' => 'test-audio-segment'],
            ['title' => 'شرح صوتي', 'duration' => 1200]
        );

        $segment = $audio->nodes()->create([
            'slug' => 'seg-1',
            'type' => 'segment',
            'title' => 'مقدمة الباب',
            'order' => 1,
            'metadata' => ['start_time' => 15.0],
            'content_html' => '<p>هذا نص المقطع الأول للتجربة</p>',
            'plain_text' => 'هذا نص المقطع الأول للتجربة',
        ]);

        $response = $this->actingAs($user)
            ->get("/studio/audio/{$audio->slug}/{$segment->id}");

        $response->assertStatus(200);

        $props = $response->viewData('page')['props'];

        $this->assertArrayHasKey('contentNode', $props);
        $this->assertNotNull($props['contentNode']);
        $this->assertEquals($segment->id, $props['contentNode']['id']);
        $this->assertEquals('مقدمة الباب', $props['contentNode']['title']);
        $this->assertEquals('<p>هذا نص المقطع الأول للتجربة</p>', $props['contentNode']['content']);
        $this->assertEquals('<p>هذا نص المقطع الأول للتجربة</p>', $props['contentNode']['html_content']);
        $this->assertEquals('<p>هذا نص المقطع الأول للتجربة</p>', $props['editorContent']);
        $this->assertEquals($segment->id, $props['activeChildId']);
        $this->assertFalse($props['isFullView']);
    }

    #[Test]
    public function can_save_edited_specific_segment(): void
    {
        $user = User::factory()->create();
        $audio = Audio::firstOrCreate(
            ['slug' => 'test-audio-save'],
            ['title' => 'تسجيل صوتي للحفظ', 'duration' => 1800]
        );

        $segment = $audio->nodes()->create([
            'slug' => 'seg-save-1',
            'type' => 'segment',
            'title' => 'العنوان الأصلي',
            'order' => 1,
            'content_html' => '<p>المحتوى الأصلي</p>',
            'plain_text' => 'المحتوى الأصلي',
        ]);

        $newHtml = '<p>المحتوى الجديد بعد التعديل والحفظ بنجاح</p>';
        $newTitle = 'العنوان المحدث';

        $response = $this->actingAs($user)
            ->postJson("/studio/audio/{$audio->slug}/{$segment->id}/save", [
                'child_id' => $segment->id,
                'title' => $newTitle,
                'content' => $newHtml,
                'html_content' => $newHtml,
                'plain_text' => strip_tags($newHtml),
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'تم الحفظ بنجاح',
        ]);
        $response->assertJsonStructure(['message', 'last_saved']);

        $segment->refresh();
        $this->assertEquals($newHtml, $segment->content_html);
        $this->assertEquals('المحتوى الجديد بعد التعديل والحفظ بنجاح', $segment->plain_text);
        $this->assertEquals($newTitle, $segment->title);
        $this->assertEquals($newHtml, $segment->content);
    }

    #[Test]
    public function can_save_segment_with_empty_content(): void
    {
        $user = User::factory()->create();
        $audio = Audio::firstOrCreate(
            ['slug' => 'test-audio-empty-save'],
            ['title' => 'صوت للحفظ الفارغ', 'duration' => 600]
        );

        $segment = $audio->nodes()->create([
            'slug' => 'seg-empty-1',
            'type' => 'segment',
            'title' => 'مقطع فارغ',
            'order' => 1,
            'content_html' => '<p>سيتم مسحه</p>',
            'plain_text' => 'سيتم مسحه',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/studio/audio/{$audio->slug}/{$segment->id}/save", [
                'child_id' => $segment->id,
                'title' => 'مقطع فارغ',
                'content' => '',
                'html_content' => '',
                'plain_text' => '',
            ]);

        $response->assertStatus(200);

        $segment->refresh();
        $this->assertEquals('', $segment->content_html);
        $this->assertEquals('', $segment->plain_text);
    }

    #[Test]
    public function content_node_to_array_includes_content_and_html_content(): void
    {
        $audio = Audio::firstOrCreate(
            ['slug' => 'test-audio-appends'],
            ['title' => 'صوت الملحقات', 'duration' => 500]
        );

        $node = $audio->nodes()->create([
            'slug' => 'node-appends-1',
            'type' => 'segment',
            'title' => 'عقدة اختبار الملحقات',
            'order' => 1,
            'content_html' => '<p>نص تجريبي</p>',
            'plain_text' => 'نص تجريبي',
        ]);

        $array = $node->toArray();

        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('html_content', $array);
        $this->assertEquals('<p>نص تجريبي</p>', $array['content']);
        $this->assertEquals('<p>نص تجريبي</p>', $array['html_content']);
    }
}
