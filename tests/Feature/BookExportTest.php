<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\ContentNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookExportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $book;
    protected $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->book = Book::factory()->create(['slug' => 'test-book']);
        $this->child = $this->book->nodes()->create([
            'title' => 'Test Chapter',
            'type' => 'chapter',
            'content_blocks' => [
                [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Hello World Arabic السلام عليكم']]
                ]
            ]
        ]);
    }

    public function test_export_to_markdown()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get(route('api.book-children.export', ['child' => $this->child->id, 'format' => 'md']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
        $this->assertStringContainsString('Hello World', $response->getContent());
        $this->assertStringContainsString('السلام عليكم', $response->getContent());
    }

    public function test_export_to_pdf()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get(route('api.book-children.export', ['child' => $this->child->id, 'format' => 'pdf']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_export_to_word()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get(route('api.book-children.export', ['child' => $this->child->id, 'format' => 'docx']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $response->headers->get('Content-Type'));
    }

    public function test_export_full_book()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->get(route('api.books.export', ['book' => $this->book->slug, 'format' => 'pdf']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
