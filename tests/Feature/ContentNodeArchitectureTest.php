<?php

namespace Tests\Feature;

use App\Models\Audio;
use App\Models\Book;
use App\Models\ContentNode;
use App\Models\Manuscript;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentNodeArchitectureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. اختبار إنشاء عقدة محتوى للكتب وحفظ كتل Tiptap بصيغة JSON
     */
    public function test_can_create_content_node_for_book_with_json_ast()
    {
        $book = Book::create([
            'title' => 'مختصر خليل',
            'author' => 'خليل بن إسحاق',
            'slug' => 'mukhtasar-khalil',
        ]);

        $node = ContentNode::create([
            'entity_type' => Book::class,
            'entity_id' => $book->id,
            'type' => 'chapter',
            'title' => 'فصل في الطهارة',
            'order' => 1,
            'content_html' => '<p>يُرْفَعُ حَدَثُ بِمُطْلَقٍ</p>',
            'plain_text' => 'يرفع حدث بمطلق',
            'content_json' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'يُرْفَعُ حَدَثُ بِمُطْلَقٍ']
                        ]
                    ]
                ]
            ],
            'metadata' => [
                'page_number' => 12,
                'hadith_count' => 0
            ]
        ]);

        $this->assertDatabaseHas('content_nodes', [
            'id' => $node->id,
            'title' => 'فصل في الطهارة',
            'entity_type' => Book::class,
            'entity_id' => $book->id,
        ]);

        // استرجاع العقدة والتحقق من تحويل الـ JSON تلقائياً لمصفوفة
        $retrieved = ContentNode::find($node->id);
        $this->assertIsArray($retrieved->content_json);
        $this->assertEquals('يُرْفَعُ حَدَثُ بِمُطْلَقٍ', $retrieved->content_json['content'][0]['content'][0]['text']);
        $this->assertEquals(12, $retrieved->metadata['page_number']);
    }

    /**
     * 2. اختبار الشجرة الهرمية (الأب والأبناء والترتيب)
     */
    public function test_hierarchical_parent_children_relationship()
    {
        $book = Book::create([
            'title' => 'رياض الصالحين',
            'slug' => 'riyad-al-salihin',
        ]);

        // عقدة الأب: باب
        $parent = ContentNode::create([
            'entity_type' => Book::class,
            'entity_id' => $book->id,
            'type' => 'volume',
            'title' => 'كتاب الإخلاص',
            'order' => 1,
        ]);

        // عقدتان فرعيتان: فصلان
        $child1 = ContentNode::create([
            'entity_type' => Book::class,
            'entity_id' => $book->id,
            'parent_id' => $parent->id,
            'type' => 'chapter',
            'title' => 'حديث إنما الأعمال بالنيات',
            'order' => 1,
        ]);

        $child2 = ContentNode::create([
            'entity_type' => Book::class,
            'entity_id' => $book->id,
            'parent_id' => $parent->id,
            'type' => 'chapter',
            'title' => 'حديث الهجرة',
            'order' => 2,
        ]);

        // التحقق من العلاقة من جهة الأب
        $this->assertCount(2, $parent->children);
        $this->assertEquals('حديث إنما الأعمال بالنيات', $parent->children->first()->title);
        $this->assertEquals('حديث الهجرة', $parent->children->last()->title);

        // التحقق من العلاقة من جهة الابن
        $this->assertEquals($parent->id, $child1->parent->id);
        $this->assertEquals('كتاب الإخلاص', $child1->parent->title);
    }

    /**
     * 3. اختبار ربط الكيانات الأربعة بعلاقة children() ومطابقتها لموديل ContentNode
     */
    public function test_entities_can_access_their_content_nodes_via_children_relation()
    {
        // كتاب
        $book = Book::create(['title' => 'كتاب تجريبي', 'slug' => 'book-1']);
        $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'فصل الكتاب',
            'order' => 1
        ]);
        $this->assertCount(1, $book->children);
        $this->assertEquals('فصل الكتاب', $book->children->first()->title);

        // صوت
        $audio = Audio::create(['title' => 'درس صوتي', 'slug' => 'audio-1']);
        $audio->nodes()->create([
            'type' => 'segment',
            'title' => 'المقطع الأول',
            'order' => 1,
            'metadata' => ['start_time' => 0.0, 'end_time' => 120.5]
        ]);
        $this->assertCount(1, $audio->children);
        $this->assertEquals(0.0, $audio->children->first()->start_time);
        $this->assertEquals(120.5, $audio->children->first()->end_time);

        // مخطوطة
        $manuscript = Manuscript::create(['title' => 'مخطوطة نادرة', 'slug' => 'ms-1']);
        $manuscript->nodes()->create([
            'type' => 'page',
            'title' => 'اللوحة 1-أ',
            'order' => 1,
            'metadata' => [
                'folio_number' => '1-أ',
                'image_url' => 'manuscripts/ms-1/page-1a.jpg'
            ]
        ]);
        $this->assertCount(1, $manuscript->children);
        $this->assertEquals('1-أ', $manuscript->children->first()->folio_number);
        $this->assertEquals('manuscripts/ms-1/page-1a.jpg', $manuscript->children->first()->image_url);
    }

    /**
     * 4. اختبار حفظ واسترجاع الحواشي العلمية وعلامات Tiptap
     */
    public function test_scholarly_footnotes_and_marks_in_content_json()
    {
        $book = Book::create(['title' => 'شرح المقدمة', 'slug' => 'sharh-muqaddimah']);

        $node = ContentNode::create([
            'entity_type' => Book::class,
            'entity_id' => $book->id,
            'type' => 'chapter',
            'title' => 'المقدمة',
            'content_json' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'قال الشيخ رحمه الله'],
                            [
                                'type' => 'text',
                                'text' => 'في حاشيته',
                                'marks' => [
                                    [
                                        'type' => 'scholarlyFootnote',
                                        'attrs' => ['content' => 'أي في النسخة الأزهرية', 'marker' => '1']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $retrieved = ContentNode::find($node->id);
        $marks = $retrieved->content_json['content'][0]['content'][1]['marks'];
        $this->assertEquals('scholarlyFootnote', $marks[0]['type']);
        $this->assertEquals('أي في النسخة الأزهرية', $marks[0]['attrs']['content']);
    }

    /**
     * 5. اختبار الحذف التلقائي المتتالي (Cascade Delete)
     */
    public function test_deleting_entity_cascades_to_content_nodes()
    {
        $book = Book::create(['title' => 'كتاب سيحذف', 'slug' => 'to-delete']);

        $book->nodes()->create([
            'type' => 'chapter',
            'title' => 'فصل سيحذف مع الكتاب',
            'order' => 1
        ]);

        $this->assertEquals(1, ContentNode::where('entity_id', $book->id)->count());

        // حذف الكتاب
        $book->forceDelete();

        // يجب أن تُحذف كل العقد التابعة له تلقائياً
        $this->assertEquals(0, ContentNode::where('entity_id', $book->id)->count());
    }

    /**
     * 6. اختبار سجل الإصدارات التاريخية (Versions History)
     */
    public function test_node_versioning_history()
    {
        $book = Book::create(['title' => 'تاريخ دمشق', 'slug' => 'tarikh-dimashq']);

        $node = ContentNode::create([
            'entity_type' => Book::class,
            'entity_id' => $book->id,
            'type' => 'chapter',
            'title' => 'ترجمة فلان',
            'content_html' => '<p>النص الأولي</p>',
        ]);

        // حفظ نسخة
        $node->createVersion('المسودة الأولى');

        // تعديل النص وحفظ نسخة ثانية
        $node->content_html = '<p>النص بعد التحقيق</p>';
        $node->createVersion('بعد التدقيق والمقابلة');

        $node->refresh();
        $this->assertCount(2, $node->versions);
        $this->assertEquals('المسودة الأولى', $node->versions[0]['description']);
        $this->assertEquals('بعد التدقيق والمقابلة', $node->versions[1]['description']);
        $this->assertEquals('<p>النص الأولي</p>', $node->versions[0]['content_html']);
    }
}
