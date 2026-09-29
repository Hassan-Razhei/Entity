<?php

namespace Tests\Feature;

use App\Models\Audio;
use App\Models\Book;
use App\Models\ContentNode;
use App\Models\Manuscript;
use App\Models\Video;
use App\Services\EntityContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_unified_content_nodes_in_postgresql_for_different_entities(): void
    {
        // 1. Create Entities in PostgreSQL
        $book = Book::factory()->create(['title' => 'Unified Book']);
        $audio = Audio::factory()->create(['title' => 'Unified Audio']);
        $manuscript = Manuscript::factory()->create(['title' => 'Unified Manuscript']);

        // 2. Create Content via ContentNode polymorphic model
        $bookNode = $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'Chapter 1',
            'order' => 1,
            'content_html' => '<p>Book chapter content</p>',
            'plain_text' => 'Book chapter content',
        ]);

        $audioNode = $audio->nodes()->create([
            'type' => 'segment',
            'title' => 'Segment 1',
            'order' => 1,
            'metadata' => ['start_time' => 0.0, 'end_time' => 60.0],
            'content_html' => '<p>Audio segment transcript</p>',
        ]);

        $manuscriptNode = $manuscript->nodes()->create([
            'type' => 'folio',
            'title' => 'Folio 1A',
            'order' => 1,
            'metadata' => ['folio_number' => '1a', 'image_url' => 'https://example.com/folio1a.jpg'],
        ]);

        // 3. Assertions in PostgreSQL database
        $this->assertDatabaseHas('content_nodes', [
            'id' => $bookNode->id,
            'entity_type' => 'book',
            'entity_id' => $book->id,
            'title' => 'Chapter 1',
        ]);

        $this->assertDatabaseHas('content_nodes', [
            'id' => $audioNode->id,
            'entity_type' => 'audio',
            'entity_id' => $audio->id,
            'title' => 'Segment 1',
        ]);

        $this->assertDatabaseHas('content_nodes', [
            'id' => $manuscriptNode->id,
            'entity_type' => 'manuscript',
            'entity_id' => $manuscript->id,
            'title' => 'Folio 1A',
        ]);

        // Test metadata virtual accessors
        $this->assertEquals(0.0, $audioNode->start_time);
        $this->assertEquals(60.0, $audioNode->end_time);
        $this->assertEquals('1a', $manuscriptNode->folio_number);
        $this->assertEquals('https://example.com/folio1a.jpg', $manuscriptNode->image_url);
    }

    #[Test]
    public function service_creates_and_retrieves_content_nodes(): void
    {
        $service = new EntityContentService();
        $book = Book::factory()->create(['title' => 'Service Book']);

        // 1. Valid Book Content (Chapter)
        $chapter = $service->createNode($book, [
            'type' => 'chapter',
            'title' => 'Valid Chapter',
            'content_html' => '<p>Chapter text</p>',
        ]);

        $this->assertInstanceOf(ContentNode::class, $chapter);
        $this->assertEquals('book', $chapter->entity_type);
        $this->assertEquals($book->id, $chapter->entity_id);

        // Retrieve node via service
        $retrieved = $service->getNode($book, $chapter->slug);
        $this->assertNotNull($retrieved);
        $this->assertEquals($chapter->id, $retrieved->id);

        $retrievedById = $service->getNodeById($book, $chapter->id);
        $this->assertEquals($chapter->id, $retrievedById->id);
    }

    #[Test]
    public function service_enforces_allowed_content_types(): void
    {
        $service = new EntityContentService();
        $book = Book::factory()->create();

        // Invalid Book Content (Segment is for audio/video) -> Should Fail
        $this->expectException(ValidationException::class);
        $service->createNode($book, [
            'type' => 'segment',
            'title' => 'Invalid Segment for Book',
        ]);
    }

    #[Test]
    public function service_manages_hierarchy_and_navigation(): void
    {
        $service = new EntityContentService();
        $book = Book::factory()->create();

        // 1. Add Part (Parent)
        $part = $service->addNode($book, 'part', 'Part One');

        // 2. Add Chapter (Child of Part)
        $chapter = $service->addNode($book, 'chapter', 'Chapter One', null, $part->id);

        // 3. Add Chapter Two (Sibling)
        $chapterTwo = $service->addNode($book, 'chapter', 'Chapter Two', null, $part->id);

        // Assert hierarchy
        $hierarchy = $service->getHierarchy($book);
        $this->assertCount(3, $hierarchy);

        // Assert navigation
        $nav = $service->getNavigation($book, $chapter);
        $this->assertEquals($part->title, $nav['prev']->title);
        $this->assertEquals($chapterTwo->title, $nav['next']->title);

        // Assert parent-child relationship on ContentNode model
        $this->assertEquals($part->id, $chapter->parent->id);
        $this->assertCount(2, $part->children);
    }

    #[Test]
    public function entities_can_access_their_content_via_nodes_and_children_relation(): void
    {
        $book = Book::factory()->create();

        $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'Content #1',
            'order' => 1,
        ]);

        $this->assertCount(1, $book->nodes);
        $this->assertEquals('Content #1', $book->nodes->first()->title);

        // Test alias children relation
        $this->assertCount(1, $book->children);
        $this->assertEquals('Content #1', $book->children->first()->title);
    }

    #[Test]
    public function deleting_an_entity_cascades_and_deletes_its_content_nodes(): void
    {
        $book = Book::factory()->create();

        $node = $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'To be deleted',
            'order' => 1,
        ]);

        $this->assertDatabaseHas('content_nodes', ['id' => $node->id, 'deleted_at' => null]);

        // Soft delete entity -> nodes are soft deleted
        $book->delete();
        $this->assertSoftDeleted('content_nodes', ['id' => $node->id]);

        // Force delete entity -> nodes are permanently deleted
        $book->forceDelete();
        $this->assertDatabaseMissing('content_nodes', ['id' => $node->id]);
    }

    #[Test]
    public function service_can_aggregate_full_content_with_markers(): void
    {
        $service = new EntityContentService();
        $audio = Audio::factory()->create(['title' => 'Audio for Aggregation']);

        $service->addNode($audio, 'segment', 'Intro Segment', 0.0);
        $service->addNode($audio, 'segment', 'Main Segment', 30.0);

        $aggregated = $service->aggregateFullContent($audio);

        $this->assertStringContainsString('structure-marker', $aggregated);
        $this->assertStringContainsString('Intro Segment', $aggregated);
        $this->assertStringContainsString('Main Segment', $aggregated);
    }
}
