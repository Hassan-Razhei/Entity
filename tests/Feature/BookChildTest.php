<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ContentNode;
use App\Services\BookContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookChildTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_book_child_node_in_postgresql(): void
    {
        // 1. Create a Book in PostgreSQL
        $book = Book::factory()->create([
            'title' => 'Test Book for ContentNode'
        ]);

        // 2. Create ContentNode in PostgreSQL
        $content = $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'Chapter 1: The Beginning',
            'order' => 1,
            'content_json' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 1],
                    'content' => [
                        ['type' => 'text', 'text' => 'Chapter 1: The Beginning']
                    ]
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Hello from PostgreSQL!']
                    ]
                ]
            ]
        ]);

        // 3. Assertions
        $this->assertNotNull($content->id);
        $this->assertEquals($book->id, $content->entity_id);
        $this->assertEquals('book', $content->entity_type);

        // Find it back from PostgreSQL
        $retrieved = ContentNode::find($content->id);
        $this->assertEquals('Chapter 1: The Beginning', $retrieved->content_json[0]['content'][0]['text']);
        $this->assertEquals('Hello from PostgreSQL!', $retrieved->content_json[1]['content'][0]['text']);
    }

    #[Test]
    public function it_can_update_nested_content_blocks(): void
    {
        $book = Book::factory()->create();

        $content = $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'Chapter Test',
            'content_json' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Old Title']]]]
        ]);

        $content->update([
            'content_json' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'New Title']]]]
        ]);

        $this->assertEquals('New Title', ContentNode::find($content->id)->content_json[0]['content'][0]['text']);
    }

    #[Test]
    public function a_book_can_access_its_children_nodes(): void
    {
        $book = Book::factory()->create();

        $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'Chapter 1'
        ]);

        $this->assertCount(1, $book->children);
        $this->assertEquals('Chapter 1', $book->children->first()->title);
    }

    #[Test]
    public function book_content_service_can_manage_hierarchy(): void
    {
        $service = new BookContentService();
        $book = Book::factory()->create();

        // Add a Part
        $part = $service->addChild($book, [
            'type' => 'part',
            'title' => 'Part One',
            'order' => 1
        ]);

        // Add a Chapter under that Part
        $chapter = $service->addChild($book, [
            'parent_id' => $part->id,
            'type' => 'chapter',
            'title' => 'Chapter One',
            'order' => 1
        ]);

        $hierarchy = $service->getHierarchy($book);

        $this->assertCount(2, $hierarchy);
        $this->assertEquals('chapter', $hierarchy->where('title', 'Chapter One')->first()->type);
        $this->assertEquals($part->id, $hierarchy->where('title', 'Chapter One')->first()->parent_id);
    }

    #[Test]
    public function it_can_add_annotated_blocks_via_service(): void
    {
        $service = new BookContentService();
        $book = Book::factory()->create();
        $child = $book->nodes()->create([
            'type' => 'masalah',
            'title' => 'Masala 1'
        ]);

        $service->addBlock($child, [
            'type' => 'paragraph',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Main text content',
                    'marks' => [
                        [
                            'type' => 'scholarlyFootnote',
                            'attrs' => ['content' => 'Footnote 1', 'marker' => '*']
                        ]
                    ]
                ]
            ]
        ]);

        $updatedChild = ContentNode::find($child->id);
        $this->assertCount(1, $updatedChild->content_json);
        $this->assertEquals('Main text content', $updatedChild->content_json[0]['content'][0]['text']);
        $this->assertEquals('scholarlyFootnote', $updatedChild->content_json[0]['content'][0]['marks'][0]['type']);
        $this->assertEquals('Footnote 1', $updatedChild->content_json[0]['content'][0]['marks'][0]['attrs']['content']);
    }

    #[Test]
    public function deleting_a_book_deletes_its_content_nodes(): void
    {
        $book = Book::factory()->create();

        $node = $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'Chapter to be deleted'
        ]);

        $this->assertDatabaseHas('content_nodes', ['id' => $node->id, 'deleted_at' => null]);

        // Soft delete book -> nodes are soft deleted
        $book->delete();
        $this->assertSoftDeleted('content_nodes', ['id' => $node->id]);

        // Force delete book -> nodes are completely removed
        $book->forceDelete();
        $this->assertDatabaseMissing('content_nodes', ['id' => $node->id]);
    }

    #[Test]
    public function legacy_model_adapters_can_create_and_query_by_parent_foreign_key(): void
    {
        $book = Book::factory()->create();
        $bookChild = \App\Models\BookChild::create([
            'book_id' => $book->id,
            'title' => 'Adapter Chapter',
            'slug' => 'adapter-chapter-' . uniqid(),
            'order' => 1,
            'content_blocks' => [['type' => 'paragraph', 'text' => 'Hello']],
        ]);

        $this->assertEquals($book->id, $bookChild->book_id);
        $this->assertEquals('book', $bookChild->entity_type);
        $this->assertEquals($book->id, $bookChild->book->id);

        $audio = \App\Models\Audio::create([
            'title' => 'Adapter Audio',
            'slug' => 'adapter-audio-' . uniqid(),
        ]);
        $segment = \App\Models\AudioSegment::create([
            'audio_id' => $audio->id,
            'title' => 'Adapter Audio Segment',
            'slug' => 'adapter-audio-seg-' . uniqid(),
            'order' => 1,
            'start_time' => 10.5,
            'end_time' => 20.5,
        ]);
        $this->assertEquals($audio->id, $segment->audio_id);
        $this->assertEquals('audio', $segment->entity_type);
        $this->assertEquals(10.5, $segment->start_time);
        $this->assertEquals($audio->id, $segment->audio->id);

        $video = \App\Models\Video::create([
            'title' => 'Adapter Video',
            'slug' => 'adapter-video-' . uniqid(),
        ]);
        $vSegment = \App\Models\VideoSegment::create([
            'video_id' => $video->id,
            'title' => 'Adapter Video Segment',
            'slug' => 'adapter-video-seg-' . uniqid(),
            'order' => 1,
            'description' => 'A test scene',
        ]);
        $this->assertEquals($video->id, $vSegment->video_id);
        $this->assertEquals('video', $vSegment->entity_type);
        $this->assertEquals('A test scene', $vSegment->description);
        $this->assertEquals($video->id, $vSegment->video->id);
    }
}
