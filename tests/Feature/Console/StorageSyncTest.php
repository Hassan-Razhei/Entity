<?php

namespace Tests\Feature\Console;

use App\Models\Audio;
use App\Models\Book;
use App\Models\ContentNode;
use App\Models\Manuscript;
use App\Models\Version;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorageSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_syncs_storage_files_recursively_and_supports_manuscript_bundles(): void
    {
        Storage::fake('public');

        // 1. Create Recursive Book Structure (Approach A: Categorization)
        Storage::disk('public')->put('books/History/Islamic/Sira.md', "# Sira\nBiography content.");

        // 2. Create Manuscript Bundle (Approach B: Single Entity Folder)
        Storage::disk('public')->put('manuscripts/Bundle_X/page1.jpg', 'img1');
        Storage::disk('public')->put('manuscripts/Bundle_X/page2.png', 'img2');

        // 3. Create Standalone Audio in nested folder
        Storage::disk('public')->put('audios/Podcasts/History/Episode1.mp3', 'mp3');

        // 4. Run Command
        $exitCode = $this->withoutMockingConsoleOutput()
            ->artisan('storage:sync', ['path' => Storage::disk('public')->path('')]);

        $this->assertEquals(0, $exitCode);

        // 5. Verify Recursive Book
        $book = Book::where('slug', 'sira')->first();
        $this->assertNotNull($book, 'Recursive Book should be created');
        // Tags should come from parent directories: History, Islamic
        $this->assertTrue($book->tags->contains('name', 'History'), 'Book should have History tag');
        $this->assertTrue($book->tags->contains('name', 'Islamic'), 'Book should have Islamic tag');

        // 6. Verify Manuscript Bundle and Pages in PostgreSQL ContentNode
        $manuscript = Manuscript::where('slug', 'bundle-x')->first();
        $this->assertNotNull($manuscript, 'Manuscript Bundle should be created');
        $this->assertEquals(2, $manuscript->children()->count(), 'Manuscript should have 2 pages');

        // 7. Verify Nested Audio & Tags
        $audio = Audio::where('file_path', 'LIKE', '%Episode1.mp3')->first();
        $this->assertNotNull($audio, 'Nested Audio should be created');
        $this->assertTrue($audio->tags->contains('name', 'Podcasts'), 'Audio should have Podcasts tag');
    }
}
