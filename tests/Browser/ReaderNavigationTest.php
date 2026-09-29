<?php

namespace Tests\Browser;

use App\Models\Book;
use App\Models\BookChild;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use PHPUnit\Framework\Attributes\Test;

class ReaderNavigationTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @test */
    public function it_can_navigate_to_child_content_via_sidebar()
    {
        $this->browse(function (Browser $browser) {
            // 1. Create Data
            $user = User::factory()->create();
            $book = Book::factory()->create([
                'title' => 'Test Navigation Book',
                'slug' => 'test-nav-book'
            ]);

            $chapter = $book->nodes()->create([
                'title' => 'Test Chapter 1',
                'slug' => 'test-chapter-1',
                'type' => 'chapter',
                'order' => 0,
                'parent_id' => null,
                'content_json' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'Content for Chapter 1']]
                        ]
                    ]
                ]
            ]);

            // 2. Login and Visit Reader
            $browser->loginAs($user)
                ->resize(1920, 1080)
                ->visit("/books/{$book->slug}/reader")
                ->waitFor('.reader-sidebar', 10) // Wait for sidebar to load
                ->waitForText('Test Navigation Book', 10)
                ->assertSee('Test Chapter 1') // Sidebar item

                // 3. Verify sidebar loaded
                ->assertSee('Test Chapter 1')

                // 4. Click chapter link
                ->clickLink('Test Chapter 1')
                ->pause(2000)

                // 5. Verify URL changed
                ->assertPathIs("/books/{$book->slug}/reader/{$chapter->_id}")
            ;
        });
    }
}
