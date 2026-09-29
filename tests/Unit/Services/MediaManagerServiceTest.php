<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Book;
use App\Models\Author;
use App\Models\Publisher;
use App\Services\MediaManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MediaManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_media_with_initial_version_and_authors()
    {
        // 1. Arrange: Use Factories
        $author = Author::factory()->create();
        $publisher = Publisher::factory()->create();

        $data = [
            'title' => 'The Factory Media',
            'type' => 'book',
            'author_ids' => [$author->id],
            'publisher_id' => $publisher->id,
            'file_path' => 'books/factory_book.pdf',
            'isbn' => '978-1234567890',
            'pages' => 300,
        ];

        // 2. Act
        $service = app(MediaManagerService::class);
        $book = $service->createMedia($data);

        // 3. Assert
        $this->assertInstanceOf(Book::class, $book);
        $this->assertEquals('The Factory Media', $book->title);

        // Assert Author attached
        $this->assertTrue($book->authors->contains($author));

        // Assert Version created
        $this->assertEquals(1, $book->versions()->count());
        $version = $book->versions->first();
        $this->assertEquals('books/factory_book.pdf', $version->file_path);
        $this->assertEquals($publisher->id, $version->publisher_id);
    }

    #[Test]
    public function it_updates_media_and_relations()
    {
        // 1. Arrange: Create initial book with author
        $author = Author::factory()->create();
        $book = Book::factory()->create(['title' => 'Old Title']);
        $book->authors()->attach($author);

        // Create initial version
        \App\Models\Version::create([
            'versionable_id' => $book->id,
            'versionable_type' => 'book',
            'pages' => 100
        ]);

        $newAuthor = Author::factory()->create();
        $newPublisher = Publisher::factory()->create();

        $updateData = [
            'title' => 'Updated Title',
            'type' => 'book',
            'description' => 'New Description',
            'author_ids' => [$newAuthor->id], // Switch author
            'publisher_id' => $newPublisher->id,
            'pages' => 500,
        ];

        // 2. Act
        $service = app(MediaManagerService::class);
        $updatedBook = $service->updateMedia($book, $updateData);

        // 3. Assert
        $this->assertEquals('Updated Title', $updatedBook->fresh()->title);
        $this->assertEquals('New Description', $updatedBook->fresh()->description);

        // Assert Author Synced
        $this->assertEquals(1, $updatedBook->authors()->count());
        $this->assertEquals($newAuthor->id, $updatedBook->authors->first()->id);

        // Assert Version Updated
        $version = $updatedBook->versions->first();
        $this->assertNotNull($version);
        $this->assertEquals($newPublisher->id, $version->publisher_id);
        $this->assertEquals(500, $version->pages);
    }
}
