<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Audio;
use App\Models\Book;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Comment;
use App\Models\Manuscript;
use App\Models\Note;
use App\Models\Series;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seed_realistic_data_command_populates_all_relationships_exhaustively()
    {
        // 1. Run the command
        $count = 3;
        $this->artisan("project:seed-realistic --count={$count}")
            ->expectsConfirmation(
                '⚠️  سيتم حذف جميع البيانات الحالية وإعادة تعبئتها. هل أنت متأكد؟',
                'yes'
            )
            ->expectsOutput("Starting exhaustive realistic data seeding (Count: {$count} for each type)...")
            ->expectsOutput("Seeding completed successfully with all relationships!")
            ->assertExitCode(0);

        // 2. Verify Entity Counts
        $this->assertEquals($count, Book::count());
        $this->assertEquals($count, Manuscript::count());
        $this->assertEquals($count, Audio::count());
        $this->assertEquals($count, Video::count());

        // 3. Verify Taxonomies (Categories & Tags)
        // Each entity MUST have at least 1 category and 1+ tags based on the seeder logic
        $allEntities = collect()->merge(Book::all())
            ->merge(Manuscript::all())
            ->merge(Audio::all())
            ->merge(Video::all());

        foreach ($allEntities as $entity) {
            $this->assertGreaterThanOrEqual(1, $entity->categories()->count(), "Entity {$entity->title} missing category");
            $this->assertGreaterThanOrEqual(1, $entity->tags()->count(), "Entity {$entity->title} missing tags");

            // Verify and check activity log
            $this->assertDatabaseHas('activities', [
                'entity_id' => $entity->id,
                'entity_type' => $entity->getMorphClass(),
                'activity_type' => 'viewed'
            ]);
        }

        // 4. Verify Nested Interactions (Comments & Notes)
        // Note: Seeder uses rand(), so we just check global presence assuming enough runs
        $this->assertGreaterThan(0, Comment::count());
        $this->assertGreaterThan(0, Note::count());

        foreach (Comment::all() as $comment) {
            $this->assertNotNull($comment->user_id);
            $this->assertNotNull($comment->entity_id);
            $this->assertNotEmpty($comment->content);
        }

        foreach (Note::all() as $note) {
            $this->assertNotNull($note->user_id);
            $this->assertNotNull($note->entity_id);
            $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $note->content); // Contains Arabic
        }

        // 5. Verify Organizations (Collections & Series)
        $this->assertGreaterThanOrEqual(3, Collection::count());
        $this->assertGreaterThanOrEqual(2, Series::count());

        foreach (Collection::all() as $collection) {
            $this->assertGreaterThanOrEqual(3, $collection->entities->count());
            $this->assertNotNull($collection->user_id);
        }

        foreach (Series::all() as $series) {
            $this->assertGreaterThanOrEqual(3, $series->entities->count());
            // Check ordering
            $positions = $series->entities->map(fn($e) => $e->pivot_data['position'])->toArray();
            $this->assertEquals($positions, array_unique($positions)); // Unique positions
        }
    }

    #[Test]
    public function storage_sync_command_scans_and_updates_database_records()
    {
        Storage::fake('public');

        // 1. Prepare Storage Files
        Storage::disk('public')->put('books/test-book.pdf', 'dummy content');
        Storage::disk('public')->put('audios/test-audio.mp3', 'dummy content');
        Storage::disk('public')->put('videos/test-video.mp4', 'dummy content');
        Storage::disk('public')->put('manuscripts/test-ms.jpg', 'dummy content');
        Storage::disk('public')->put('books/ignored.exe', 'dummy content'); // Truly ignored

        // 2. Run Sync
        $this->artisan('storage:sync')
            ->expectsOutput('Starting storage synchronization...')
            ->expectsOutput('Synchronization completed successfully!')
            ->assertExitCode(0);

        // 3. Verify Database Records
        // A. Books should have a related Version
        $this->assertDatabaseHas('books', ['title' => 'Test Book']);
        $book = Book::where('title', 'Test Book')->first();
        $this->assertDatabaseHas('versions', [
            'versionable_id' => $book->id,
            'versionable_type' => 'book',
            'file_path' => 'books/test-book.pdf'
        ]);

        // B. Other types also have versions now
        $this->assertDatabaseHas('audios', ['title' => 'Test Audio']);
        $audio = Audio::where('title', 'Test Audio')->first();
        $this->assertDatabaseHas('versions', [
            'versionable_id' => $audio->id,
            'versionable_type' => 'audio',
            'file_path' => 'audios/test-audio.mp3'
        ]);

        $this->assertDatabaseHas('manuscripts', ['title' => 'Test Ms']);
        $this->assertDatabaseCount('books', 1); // ignored.exe ignored

        // 4. Test --force flag (using Audio as it uses the standard updateOrCreate logic)
        $audio = Audio::first();
        $audio->update(['description' => 'Modified']);

        $this->artisan('storage:sync --force')
            ->assertExitCode(0);

        $this->assertEquals('Automatically synced from storage.', Audio::first()->description);
    }
}
