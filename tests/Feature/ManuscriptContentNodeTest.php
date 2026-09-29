<?php

namespace Tests\Feature;

use App\Models\Manuscript;
use App\Models\ContentNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verify PostgreSQL Manuscript-ContentNode Relationship Integrity
 */
class ManuscriptContentNodeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function manuscript_can_access_content_nodes(): void
    {
        // 1. Create a Manuscript in PostgreSQL with metadata fields
        $manuscript = Manuscript::create([
            'title' => 'Test Manuscript for PostgreSQL',
            'slug' => 'test-ms-' . uniqid(),
            'code' => 'MS_PG_TEST',
            'scribe' => 'Test Scribe',
            'manuscript_century' => '9',
            'manuscript_century_label' => '9 هـ',
        ]);

        // 2. Create a ContentNode in PostgreSQL linked to it
        $page = $manuscript->nodes()->create([
            'slug' => 'page-1-' . uniqid(),
            'type' => 'folio',
            'title' => 'الصفحة الأولى',
            'order' => 1,
            'metadata' => ['folio_number' => '1أ'],
            'content_html' => '<p>محتوى الصفحة الأولى</p>',
        ]);

        // 3. Test: Can Manuscript access its PostgreSQL children?
        $this->assertNotNull($manuscript->children);
        $this->assertEquals(1, $manuscript->children->count());

        $retrievedPage = $manuscript->children->first();
        $this->assertEquals('الصفحة الأولى', $retrievedPage->title);
        $this->assertEquals($manuscript->id, $retrievedPage->entity_id);
        $this->assertEquals('1أ', $retrievedPage->folio_number);
    }

    #[Test]
    public function eager_loading_children_works_cleanly_for_multiple_manuscripts(): void
    {
        $m1 = Manuscript::create([
            'title' => 'Manuscript 1',
            'slug' => 'm1-' . uniqid(),
            'scribe' => 'Scribe A',
            'inscriptions' => 'تملك: المكتبة',
        ]);

        $m2 = Manuscript::create([
            'title' => 'Manuscript 2',
            'slug' => 'm2-' . uniqid(),
            'script_type' => 'نسخ',
            'dimensions' => '20x15',
        ]);

        // Create pages for each
        $m1->nodes()->create([
            'slug' => 'p1-' . uniqid(),
            'type' => 'folio',
            'title' => 'Page for M1',
            'order' => 1,
        ]);

        $m2->nodes()->create([
            'slug' => 'p2-' . uniqid(),
            'type' => 'folio',
            'title' => 'Page for M2',
            'order' => 1,
        ]);

        // Verify eager loading works seamlessly on PostgreSQL
        $manuscripts = Manuscript::with('children')->whereIn('id', [$m1->id, $m2->id])->get();

        $this->assertEquals(2, $manuscripts->count());
        foreach ($manuscripts as $manuscript) {
            $this->assertEquals(1, $manuscript->children->count());
        }
    }

    #[Test]
    public function manuscript_page_legacy_adapter_works_seamlessly(): void
    {
        $manuscript = Manuscript::create([
            'title' => 'Adapter Test Manuscript',
            'slug' => 'adapter-ms-' . uniqid(),
        ]);

        $page = \App\Models\ManuscriptPage::create([
            'manuscript_id' => $manuscript->id,
            'slug' => 'legacy-page-' . uniqid(),
            'title' => 'الصفحة التجريبية للمحول',
            'order' => 1,
            'folio_number' => '2ب',
            'content' => 'محتوى نصي قديم',
        ]);

        $this->assertEquals($manuscript->id, $page->manuscript_id);
        $this->assertEquals('2ب', $page->folio_number);
        $this->assertEquals('محتوى نصي قديم', $page->content);
        $this->assertEquals('manuscript', $page->entity_type);
        $this->assertEquals($manuscript->id, $page->manuscript->id);
    }
}
