<?php

namespace Tests\Feature\Editor;

use App\Models\Audio;
use App\Models\Book;
use App\Models\ContentNode;
use App\Models\Manuscript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolymorphicSaveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_save_book_content(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => 'Polymorphic Book']);
        $chapter = $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'الفصل الأول',
            'order' => 1,
            'content_html' => '<p>قديم</p>',
        ]);

        $response = $this->actingAs($user)->post("/studio/book/{$book->slug}/{$chapter->id}/save", [
            'content' => '<p>محتوى كتاب جديد معدل</p>',
            'child_id' => $chapter->id,
            'title' => 'الفصل الأول بعد التعديل',
        ]);

        $response->assertStatus(200);

        $chapter->refresh();
        $this->assertEquals('<p>محتوى كتاب جديد معدل</p>', $chapter->content_html);
        $this->assertEquals('الفصل الأول بعد التعديل', $chapter->title);
    }

    #[Test]
    public function can_save_manuscript_transcription(): void
    {
        $user = User::factory()->create();
        $manuscript = Manuscript::factory()->create(['title' => 'Polymorphic Manuscript']);
        $folio = $manuscript->nodes()->create([
            'type' => 'folio',
            'title' => 'لوحة 1أ',
            'order' => 1,
            'metadata' => ['folio_number' => '1أ'],
            'content_html' => '<p>نص اللوحة القديم</p>',
        ]);

        $response = $this->actingAs($user)->post("/studio/manuscript/{$manuscript->slug}/{$folio->id}/save", [
            'content' => '<p>تحقيق جديد لنص اللوحة</p>',
            'child_id' => $folio->id,
        ]);

        $response->assertStatus(200);

        $folio->refresh();
        $this->assertEquals('<p>تحقيق جديد لنص اللوحة</p>', $folio->content_html);
    }

    #[Test]
    public function can_save_media_transcription(): void
    {
        $user = User::factory()->create();
        $audio = Audio::factory()->create(['title' => 'Polymorphic Audio']);
        $segment = $audio->nodes()->create([
            'type' => 'segment',
            'title' => 'المقطع 1',
            'order' => 1,
            'metadata' => ['start_time' => 0.0],
            'content_html' => '<p>تفريغ قديم</p>',
        ]);

        $response = $this->actingAs($user)->post("/studio/audio/{$audio->slug}/{$segment->id}/save", [
            'content' => '<p>تفريغ صوتي منقح ودقيق</p>',
            'child_id' => $segment->id,
        ]);

        $response->assertStatus(200);

        $segment->refresh();
        $this->assertEquals('<p>تفريغ صوتي منقح ودقيق</p>', $segment->content_html);
    }
}
