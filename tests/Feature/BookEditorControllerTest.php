<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ContentNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $book;
    protected $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->book = Book::factory()->create();
        $this->child = $this->book->nodes()->create([
            'title' => 'Initial Chapter',
            'type' => 'chapter',
            'content_json' => [['type' => 'paragraph', 'content' => 'Old content']]
        ]);
    }

    #[Test]
    public function test_save_content_updates_data_and_sets_protection(): void
    {
        $newContent = [['type' => 'paragraph', 'content' => 'New modified content']];

        $response = $this->actingAs($this->user)
            ->postJson(route('api.book-children.save', $this->child->id), [
                'content_blocks' => $newContent
            ]);

        $response->assertStatus(200);

        $this->child->refresh();
        $this->assertEquals($newContent, $this->child->content_blocks);
        $this->assertTrue($this->child->is_manually_edited);
        $this->assertCount(1, $this->child->versions);
    }

    #[Test]
    public function test_restore_version_reverts_content(): void
    {
        // First, save something to create a version
        $this->child->createVersion('Snapshot 1');
        $oldContent = $this->child->content_blocks;

        $this->child->update([
            'content_blocks' => [['type' => 'paragraph', 'content' => 'Damaged content']]
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.book-children.restore', [$this->child->id, 0]));

        $response->assertStatus(200);

        $this->child->refresh();
        $this->assertEquals($oldContent, $this->child->content_blocks);
    }
}
