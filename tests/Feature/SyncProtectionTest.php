<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ContentNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncProtectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function storage_sync_skips_manually_edited_units(): void
    {
        // 1. Setup a book and a storage file
        Storage::fake('public');
        $book = Book::factory()->create(['slug' => 'test-book']);
        $filePath = 'books/test-book.md';
        Storage::disk('public')->put($filePath, "# Chapter 1\nFile Content");

        // 2. Initial Sync
        $this->artisan('storage:sync', ['path' => Storage::disk('public')->path('')]);
        $this->assertDatabaseHas('content_nodes', ['title' => 'Chapter 1', 'entity_id' => $book->id]);

        // 3. Mark as manually edited
        $child = $book->nodes()->where('title', 'Chapter 1')->first();
        $child->update([
            'content_blocks' => [['type' => 'paragraph', 'content' => 'Manual Edit']],
            'is_manually_edited' => true
        ]);

        // 4. Modify File
        Storage::disk('public')->put($filePath, "# Chapter 1\nChanged File Content");

        // 5. Sync again
        $this->artisan('storage:sync', ['path' => Storage::disk('public')->path('')]);

        // 6. Verify Chapter 1 still has 'Manual Edit' content
        $child->refresh();
        $this->assertEquals('Manual Edit', $child->content_blocks[0]['content']);
        $this->assertTrue($child->is_manually_edited);
    }
}
