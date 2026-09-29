<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookChild;
use App\Services\BookContentService;
use Illuminate\Database\Seeder;

class BookContentSeeder extends Seeder
{
    public function run(): void
    {
        $service = new BookContentService();

        // 1. Create a MySQL Book
        $book = Book::firstOrCreate(
            ['slug' => 'fath-al-bari'],
            [
                'title' => 'فتح الباري شرح صحيح البخاري',
                'description' => 'من أهم شروح صحيح البخاري وأجمعها.',
                'author' => 'ابن حجر العسقلاني'
            ]
        );

        // 2. Clear existing content for this book
        $book->nodes()->delete();

        // 3. Hierarchy: Sub-book (العلم)
        $subBook = $service->addChild($book, [
            'type' => 'sub-book',
            'title' => 'كتاب العلم',
            'order' => 1
        ]);

        // 4. Hierarchy: Part (باب كيف كان بدء الوحي)
        $part = $service->addChild($book, [
            'parent_id' => $subBook->id,
            'type' => 'part',
            'title' => 'باب: كيف كان بدء الوحي إلى رسول الله ﷺ',
            'order' => 1
        ]);

        // 5. Hierarchy: Chapter (حديث إنما الأعمال بالنيات)
        $chapter = $service->addChild($book, [
            'parent_id' => $part->id,
            'type' => 'chapter',
            'title' => 'الحديث الأول: إنما الأعمال بالنيات',
            'order' => 1
        ]);

        // 6. Add Content Blocks to the Chapter
        $service->addBlock($chapter, [
            'type' => 'paragraph',
            'body' => 'حَدَّثَنَا عَبْدُ اللَّهِ بْنُ يُوسُفَ، قَالَ أَخْبَرَنَا مَالِكٌ، عَنْ يَحْيَى بْنِ سَعِيدٍ، عَنْ مُحَمَّدِ بْنِ إِبْرَاهِيمَ التَّيْمِيِّ، أَنَّهُ سَمِعَ عَلْقَمَةَ بْنَ وَقَّاصٍ اللَّيْثِيَّ، يَقُولُ سَمِعْتُ عُمَرَ بْنَ الْخَطَّابِ ـ رضى الله عنه ـ عَلَى الْمِنْبَرِ قَالَ سَمِعْتُ رَسُولَ اللَّهِ ﷺ يَقُولُ: [1] "إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ، وَإِنَّمَا لِكُلِّ امْرِئٍ مَا نَوَى..." [2]',
            'annotations' => [
                [
                    'type' => 'footnote',
                    'marker' => '[1]',
                    'content' => 'هذا الحديث أصل من أصول الدين، وتواتر العمل به.'
                ],
                [
                    'type' => 'footnote',
                    'marker' => '[2]',
                    'content' => 'رواه البخاري في صحيحه في سبعة مواضع.'
                ]
            ]
        ]);

        $service->addBlock($chapter, [
            'type' => 'verse',
            'first_part' => 'إذا نَـوى المَرءُ خيراً جاءَهُ خَــبَرٌ',
            'second_part' => 'مِن السَّماءِ بِمَا في القَلبِ يَنطَوِي',
            'annotations' => [
                [
                    'type' => 'comment',
                    'author' => 'المحقق السلفي',
                    'content' => 'بيت شعري لطيف يستشهد به في فضل النية الصادقة.'
                ]
            ]
        ]);

        // 7. Add another Chapter for testing navigation
        $service->addChild($book, [
            'parent_id' => $part->id,
            'type' => 'chapter',
            'title' => 'مبحث في سند الحديث ورجاله',
            'order' => 2
        ]);
        
        $this->command->info('Premium Content Seeded Successfully for: ' . $book->title);
    }
}
