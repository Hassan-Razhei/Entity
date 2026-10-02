<?php

namespace App\Console\Commands;

use App\Enums\EntityType;
use App\Enums\ContentNodeType;
use Illuminate\Console\Command;

use App\Models\User;
use App\Models\Book;
use App\Models\Audio;
use App\Models\Video;
use App\Models\Manuscript;
use App\Models\Author;
use App\Models\Publisher;
use App\Models\Booker;
use App\Models\Version;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Note;
use App\Models\Collection;
use App\Models\Series;
use App\Models\Shelf;
use App\Models\ContentNode;
use App\Services\BookContentService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedRealisticData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:seed-realistic
                            {--count=10 : The number of entities to create for each type}
                            {--force : تجاوز الحماية (مطلوب في بيئة الإنتاج)}
                            {--token= : مفتاح التأكيد السري (مطلوب مع --force في الإنتاج)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with exhaustive and realistic Arabic data for testing and development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ─── حماية بيئة الإنتاج ───────────────────────────────────────────
        if (app()->environment('production')) {
            if (!$this->option('force')) {
                $this->error('⛔  هذا الأمر محظور في بيئة الإنتاج.');
                $this->line('    استخدم --force مع --token=<المفتاح_السري> إذا كنت مُخوَّلاً.');
                return Command::FAILURE;
            }

            // التحقق من المفتاح السري
            $requiredToken = config('app.seed_secret', env('SEED_SECRET'));
            $providedToken = $this->option('token');

            if (empty($requiredToken)) {
                $this->error('⛔  SEED_SECRET غير محدد في ملف .env — لا يمكن تفعيل --force.');
                return Command::FAILURE;
            }

            if (!hash_equals((string) $requiredToken, (string) $providedToken)) {
                $this->error('⛔  المفتاح السري غير صحيح.');
                return Command::FAILURE;
            }

            $this->warn('⚠️  أنت على وشك حذف جميع بيانات الإنتاج وإعادة التعبئة!');
        }
        // ─── تأكيد المستخدم (فقط عند التشغيل التفاعلي من CLI) ───────────
        // عند الاستدعاء من الويب (بدون TTY) يتم تخطي التأكيد
        // لأن SystemController تحقق بالفعل من صلاحية المستخدم
        $isTty = stream_isatty(STDOUT);
        if ($isTty && !app()->runningUnitTests() && !$this->option('force')) {
            if (!$this->confirm('⚠️  سيتم حذف جميع البيانات الحالية وإعادة تعبئتها. هل أنت متأكد؟', false)) {
                $this->info('تم الإلغاء.');
                return Command::SUCCESS;
            }
        }
        // ──────────────────────────────────────────────────────────────────


        $count = (int) $this->option('count');
        $this->info("Starting exhaustive realistic data seeding (Count: {$count} for each type)...");

        // 0. Clear Existing Data
        $this->warn("Clearing existing data...");
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Book::query()->truncate();
        Audio::query()->truncate();
        Video::query()->truncate();
        Manuscript::query()->truncate();
        Author::query()->truncate();
        Publisher::query()->truncate();
        Booker::query()->truncate();
        Category::query()->truncate();
        Tag::query()->truncate();
        Activity::query()->truncate();
        Comment::query()->truncate();
        Note::query()->truncate();
        Collection::query()->truncate();
        Series::query()->truncate();
        Shelf::query()->truncate();
        \App\Models\ContentNode::query()->truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        // 1. Core Users
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password')]
        );
        if (User::query()->count() < 5)
            User::factory(5)->create();
        $users = User::query()->get();

        // 2. Core Taxonomies
        $categories = collect([
            'التاريخ العربي',
            'علوم القرآن',
            'الفقه وأصوله',
            'الأدب والشعر',
            'الفلسفة والمنطق',
            'المخطوطات القديمة',
            'السيرة النبوية',
            'الطب العربي',
            'الفلك بنظرة إسلامية',
            'العمارة الإسلامية'
        ])->map(fn($name) => Category::query()->firstOrCreate(['name' => $name]));

        $tags = collect([
            'نادر',
            'محقق',
            'نسخة أصلية',
            'العصر العباسي',
            'الأندلس',
            'ملون',
            'مترجم',
            'شرح',
            'متن',
            'حاشية'
        ])->map(fn($name) => Tag::query()->firstOrCreate(['name' => $name]));

        // --- NEW: Topic Hierarchy ---
        $this->info("Seeding Topic Hierarchy...");
        $topicTree = [
            'العلوم الشرعية' => [
                'التفسير' => ['تفسير القرآن الكريم', 'أسباب النزول'],
                'الحديث' => ['الصحاح', 'السنن', 'المصطلح'],
                'الفقه' => ['فقه العبادات', 'فقه المعاملات'],
            ],
            'الفنون والآداب' => [
                'الشعر العربي' => ['المعلقات', 'شعر الزهد'],
                'النثر' => ['الأمثال', 'المقامات'],
            ],
            'العلوم التطبيقية' => [
                'الطب القديم' => ['قانون ابن سينا'],
                'الفلك' => ['الإصطرلاب'],
            ]
        ];

        foreach ($topicTree as $parentName => $subTopics) {
            $parent = \App\Models\Topic::query()->firstOrCreate(['name' => $parentName]);
            foreach ($subTopics as $subName => $children) {
                $sub = \App\Models\Topic::query()->firstOrCreate(['name' => $subName, 'parent_id' => $parent->id]);
                foreach ($children as $childName) {
                    \App\Models\Topic::query()->firstOrCreate(['name' => $childName, 'parent_id' => $sub->id]);
                }
            }
        }
        $topics = \App\Models\Topic::query()->get();

        // 3. New Ecosystem Data (Authors, Publishers, Bookers)
        $this->info("Seeding Authors, Publishers and Contributors...");

        $authorsList = [
            ['name' => 'ابن خلدون', 'madhab' => 'مالكي', 'region' => 'أندلسي', 'century' => 'القرن الثامن'],
            ['name' => 'البخاري', 'madhab' => 'شافعي', 'region' => 'أوزبكي', 'century' => 'القرن الثالث'],
            ['name' => 'الجاحظ', 'madhab' => 'معتزلي', 'region' => 'عراقي', 'century' => 'القرن الثالث'],
            ['name' => 'المتنبي', 'madhab' => null, 'region' => 'عراقي', 'century' => 'القرن الرابع'],
            ['name' => 'ابن رشد', 'madhab' => 'مالكي', 'region' => 'أندلسي', 'century' => 'القرن السادس'],
            ['name' => 'نجيب محفوظ', 'madhab' => null, 'region' => 'مصري', 'century' => 'القرن العشرين'],
            ['name' => 'طه حسين', 'madhab' => null, 'region' => 'مصري', 'century' => 'القرن العشرين'],
            ['name' => 'ابن المقفع', 'madhab' => null, 'region' => 'فارسي', 'century' => 'القرن الثاني'],
            ['name' => 'الشافعي', 'madhab' => 'شافعي', 'region' => 'مكي', 'century' => 'القرن الثاني'],
            ['name' => 'المنشاوي', 'madhab' => 'شافعي', 'region' => 'مصري', 'century' => 'القرن العشرين'],
            ['name' => 'د. السويدان', 'madhab' => null, 'region' => 'كويتي', 'century' => 'القرن الواحد والعشرين']
        ];
        
        $authors = collect($authorsList)->map(fn($data) => Author::query()->firstOrCreate(
            ['name' => $data['name']],
            [
                'slug' => Str::slug($data['name'], '-', null),
                'madhab' => $data['madhab'],
                'original_region' => $data['region'],
                'century_lived' => $data['century']
            ]
        ));

        $publishersList = ['دار المعرفة', 'دار الشروق', 'مكتبة العبيكان', 'عالم المعرفة', 'مركز دراسات الوحدة العربية', 'مؤسسة التراث', 'إذاعة القرآن الكريم'];
        $publishers = collect($publishersList)->map(fn($name) => Publisher::query()->firstOrCreate(
            ['name' => $name],
            ['slug' => Str::slug($name, '-', null)]
        ));

        $bookersList = ['أحمد شاكر', 'محمد فؤاد عبد الباقي', 'ناصر الدين الألباني', 'بشار عواد معروف'];
        $bookers = collect($bookersList)->map(fn($name) => Booker::query()->firstOrCreate(
            ['name' => $name],
            ['slug' => Str::slug($name, '-', null)]
        ));

        // 4. Realistic Datasets
        $dataSets = [
            'book' => [
                'items' => [
                    ['title' => 'مقدمة ابن خلدون', 'author' => 'ابن خلدون'],
                    ['title' => 'صحيح البخاري', 'author' => 'البخاري'],
                    ['title' => 'كتاب الحيوان', 'author' => 'الجاحظ'],
                    ['title' => 'ديوان المتنبي', 'author' => 'المتنبي'],
                    ['title' => 'تهافت التهافت', 'author' => 'ابن رشد'],
                    ['title' => 'الأغاني للأصفهاني', 'author' => 'الجاحظ'],
                    ['title' => 'الرسالة للشافعي', 'author' => 'الشافعي'],
                    ['title' => 'فتوح البلدان للحموي', 'author' => 'بشار عواد معروف'],
                    ['title' => 'تاريخ دمشق لابن عساكر', 'author' => 'ابن خلدون'],
                    ['title' => 'وفيات الأعيان لابن خلكان', 'author' => 'الجاحظ'],
                ]
            ],
            'manuscript' => [
                'items' => [
                    [
                        'title' => 'مخطوط كليلة ودمنة',
                        'author' => 'ابن المقفع',
                        'file_source' => 'https://upload.wikimedia.org/wikipedia/commons/5/52/Avicenna_Canon_of_Medicine.jpg',
                        'filename' => 'manuscripts/sample-1.jpg'
                    ],
                    [
                        'title' => 'رسالة الشافعي الأصلية',
                        'author' => 'الشافعي',
                        'file_source' => 'https://upload.wikimedia.org/wikipedia/commons/5/52/Avicenna_Canon_of_Medicine.jpg',
                        'filename' => 'manuscripts/sample-2.jpg'
                    ],
                    [
                        'title' => 'مصحف مذهب نادر',
                        'author' => 'مجهول',
                        'file_source' => 'https://upload.wikimedia.org/wikipedia/commons/5/52/Avicenna_Canon_of_Medicine.jpg',
                        'filename' => 'manuscripts/sample-3.jpg'
                    ],
                ]
            ],
            'audio' => [
                'items' => [
                    [
                        'title' => 'شرح ألفية ابن مالك',
                        'author' => 'ابن مالك',
                        'file_source' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                        'filename' => 'audio/sample-1.mp3'
                    ],
                    [
                        'title' => 'تلاوات المنشاوي',
                        'author' => 'المنشاوي',
                        'file_source' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
                        'filename' => 'audio/sample-2.mp3'
                    ],
                    [
                        'title' => 'محاضرة في التاريخ',
                        'author' => 'د. السويدان',
                        'file_source' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
                        'filename' => 'audio/sample-3.mp3'
                    ],
                ]
            ],
            'video' => [
                'items' => [
                    [
                        'title' => 'وثائقي العمارة الإسلامية',
                        'author' => 'مركز التراث',
                        'file_source' => 'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/360/Big_Buck_Bunny_360_10s_1MB.mp4',
                        'filename' => 'videos/sample-1.mp4'
                    ],
                    [
                        'title' => 'ندوة المخطوطات الدولية',
                        'author' => 'مؤسسة التراث',
                        'file_source' => 'https://test-videos.co.uk/vids/jellyfish/mp4/h264/360/Jellyfish_360_10s_1MB.mp4',
                        'filename' => 'videos/sample-2.mp4'
                    ],
                ]
            ],
            'shelf' => [
                'items' => [
                    ['location_code' => 'A-101', 'capacity' => 50],
                    ['location_code' => 'B-202', 'capacity' => 100],
                    ['location_code' => 'C-303', 'capacity' => 75],
                    ['location_code' => 'D-404', 'capacity' => 120],
                ]
            ]
        ];

        $allEntities = collect();

        // 5. Seeding Entities (Main Loop)
        foreach ($dataSets as $type => $set) {
            $this->info("Seeding {$type}s...");
            $bar = $this->output->createProgressBar($count);
            $bar->start();

            for ($i = 0; $i < $count; $i++) {
                $modelClass = match ($type) {
                    'book' => Book::class,
                    'manuscript' => Manuscript::class,
                    'audio' => Audio::class,
                    'video' => Video::class,
                    'shelf' => Shelf::class,
                };

                $itemData = $set['items'][$i % count($set['items'])];

                if ($type === 'shelf') {
                    $location_code = $itemData['location_code'];
                    if ($i >= count($set['items'])) {
                        $location_code .= "-" . ($i + 1);
                    }
                    $entity = Shelf::query()->create([
                        'location_code' => $location_code,
                        'capacity' => $itemData['capacity']
                    ]);
                    $bar->advance();
                    continue;
                }

                $title = $itemData['title'];

                // If we are creating more than the unique titles, add a suffix to avoid duplicate slugs
                if ($i >= count($set['items'])) {
                    $title .= " (" . (string) ($i + 1) . ")";
                }

                $attributes = [
                    'title' => $title,
                    'description' => "وصف تجريبي لـ {$title}. هذا العمل يعتبر ركيزة أساسية في مكتبتنا الرقمية ويوفر مادة علمية غنية للباحثين والقراء المهتمين بالتراث العربي والإسلامي.",
                ];

                // Add versioning code (group every 3 items together)
                if ($type !== 'shelf') {
                    $groupIndex = intdiv($i, 3);
                    $attributes['code'] = strtoupper($type) . '_GROUP_' . $groupIndex;
                }

                if (EntityType::tryFrom($type) === EntityType::MANUSCRIPT) {
                    $centuryNum = rand(2, 14);
                    // Standard Schema Fields
                    $attributes['manuscript_century'] = (string) $centuryNum;
                    $attributes['manuscript_century_label'] = $centuryNum . ' هـ';
                    
                    // Metadata Helpers
                    $scribes = ['محمد بن أحمد الكاتب', 'علي بن عبدالله النساخ', 'أحمد الأنصاري', 'ابن البواب', 'مجهول'];
                    $scriptTypes = ['نسخ', 'كوفي', 'ديواني', 'رقعة', 'ثلث'];
                    $locations = ['دمشق', 'القاهرة', 'اسطنبول', 'بغداد', 'المدينة المنورة'];
                    
                    // Rich Manuscript Metadata
                    $attributes['original_title'] = $title;
                    $attributes['catalog_number'] = 'MS-' . rand(1000, 9999) . '-' . strtoupper(substr(md5($title), 0, 2));
                    $attributes['is_autograph'] = (rand(1, 10) > 8); // 20% likely to be chronograph
                    $attributes['scribe'] = $scribes[array_rand($scribes)];
                    $attributes['copy_date'] = rand(200, 1400) . ' هـ';
                    $attributes['parts'] = (string) rand(1, 10);
                    $attributes['script_type'] = $scriptTypes[array_rand($scriptTypes)];
                    $attributes['dimensions'] = rand(15, 30) . 'x' . rand(10, 25) . ' سم';
                    $attributes['lines_per_page'] = rand(15, 35);
                    $attributes['inscriptions'] = 'تملك: ' . $locations[array_rand($locations)] . ' - قراءة وسماع';
                    $attributes['notes'] = 'نسخة نفيسة بخط جميل ومقروء، مع حواشٍ قيّمة.';
                    $attributes['location'] = $locations[array_rand($locations)];
                    
                    // New Columns Request
                    $attributes['pages'] = rand(50, 500);
                    $attributes['manuscript_start'] = "بداية المخطوط: الحمد لله رب العالمين... (نص عشوائي للمقدمة)";
                    $attributes['manuscript_end'] = "...وهذا آخر ما تيسر إيراده، والحمد لله وحده.";
                    
                } elseif (EntityType::tryFrom($type)?->supportsDuration()) {
                    $attributes['duration'] = rand(300, 3600);
                }

                // --- Generic File Handling (Search Local or Download) ---
                // This now applies to ALL types (Book, Manuscript, Audio, Video)
                if ($type !== 'shelf') {
                    $typeDir = $type . 's'; // books, manuscripts, etc.
                    $fallbackName = "sample-" . ($i % 3 + 1) . "." . (EntityType::tryFrom($type)?->defaultFormat() ?? 'pdf');
                    // Manuscript fallback uses existing logic if present, or generic
                    if (isset($itemData['filename'])) {
                        $targetFilename = $itemData['filename'];
                    } else {
                         $targetFilename = "{$typeDir}/{$fallbackName}";
                    }

                    // Search function
                    $foundPath = $this->searchLocalFile($typeDir, $title, $targetFilename);

                    if ($foundPath !== null) {
                        // Found a local file!
                        $attributes['file_path'] = $foundPath;
                        if (EntityType::tryFrom($type) === EntityType::MANUSCRIPT) $attributes['cover_path'] = $foundPath;
                        $this->line("    [+] Linked local file: $foundPath");
                    } else {
                        // Download Logic (Fallback)
                        if (isset($itemData['file_source']) && isset($itemData['filename'])) {
                             $this->ensureFileExists($itemData['file_source'], $itemData['filename']);
                             $attributes['file_path'] = $itemData['filename'];
                             if (EntityType::tryFrom($type) === EntityType::MANUSCRIPT) $attributes['cover_path'] = $itemData['filename'];
                        }
                    }
                }

                $entity = $modelClass::query()->create($attributes);
                $allEntities->push($entity);

                // --- Digital Content Nodes Seeding (Comprehensive PostgreSQL) ---
                $contentService = app(\App\Services\EntityContentService::class);
                if ($type === 'book') {
                    // Level 1: Sub-book (الكتاب الفرعي)
                    $subBookCount = rand(1, 2);
                    for ($sb = 1; $sb <= $subBookCount; $sb++) {
                        $nodeTitle = "كتاب " . ($sb === 1 ? 'المقدمات' : 'الأحكام');
                        $nodeId = (string) Str::uuid();
                        $nodeContent = '<p>مقدمة للكتاب الفرعي...</p>';
                        
                        $subBook = $contentService->createNode($entity, [
                            '_id' => $nodeId,
                            'type' => ContentNodeType::SUB_BOOK->value,
                            'title' => $nodeTitle,
                            'content' => $nodeContent,
                            'json_content' => $this->generateJsonContent($nodeContent),
                            'plain_text' => strip_tags($nodeContent),
                            'slug' => 'sub-book-' . $sb . '-' . mb_substr($entity->slug, 0, 4),
                            'order' => $sb,
                        ]);

                        // Level 2: Part (الجزء)
                        $partCount = rand(1, 2);
                        for ($p = 1; $p <= $partCount; $p++) {
                            $nodeTitle = "الجزء {$p}";
                            $nodeId = (string) Str::uuid();
                            $nodeContent = '<p>مقدمة الجزء...</p>';

                            $part = $contentService->createNode($entity, [
                                '_id' => $nodeId,
                                'parent_id' => $subBook->id,
                                'type' => ContentNodeType::PART->value,
                                'title' => $nodeTitle,
                                'content' => $nodeContent,
                                'json_content' => $this->generateJsonContent($nodeContent),
                                'plain_text' => strip_tags($nodeContent),
                                'slug' => 'part-' . $p . '-sb-' . $sb . '-' . mb_substr($entity->slug, 0, 4),
                                'order' => $p
                            ]);

                            // Level 3: Chapter (الفصل) - For simplicity, skipping Bab level
                            $chapCount = rand(2, 4);
                            for ($c = 1; $c <= $chapCount; $c++) {
                                $nodeTitle = "فصل {$c}: في المسائل المهمة";
                                $nodeId = (string) Str::uuid();
                                $nodeContent = "<p>هذا هو محتوى الفصل رقم {$c}. يحتوي على نصوص وتفريعات.</p>";

                                $chapter = $contentService->createNode($entity, [
                                    '_id' => $nodeId,
                                    'parent_id' => $part->id,
                                    'type' => ContentNodeType::CHAPTER->value,
                                    'title' => $nodeTitle,
                                    'content' => $nodeContent,
                                    'json_content' => $this->generateJsonContent($nodeContent),
                                    'plain_text' => strip_tags($nodeContent),
                                    'slug' => 'chapter-' . $c . '-p-' . $p . '-' . mb_substr($entity->slug, 0, 4),
                                    'order' => $c
                                ]);
                            }
                        }
                    }
                } elseif (EntityType::tryFrom($type) === EntityType::MANUSCRIPT) {
                    // Create 5 "Pages" for Manuscript
                    for ($p = 1; $p <= 5; $p++) {
                        $pageTitles = [1 => 'الأولى', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة', 5 => 'الخامسة'];
                        $pageNames = [1 => 'الأولى', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة', 5 => 'الخامسة'];
                        $nodeTitle = 'الصفحة ' . ($pageNames[$p] ?? $p);
                        $nodeId = (string) Str::uuid();
                        $nodeContent = "<p>محتوى الصفحة {$p} من المخطوطة " . $entity->title . "...</p>";

                        $contentService->createNode($entity, [
                            '_id' => $nodeId,
                            'type' => ContentNodeType::PAGE->value,
                            'title' => $nodeTitle,
                            'slug' => "page-{$p}-" . mb_substr($entity->slug, 0, 4),
                            'content' => $nodeContent,
                            'json_content' => $this->generateJsonContent($nodeContent),
                            'plain_text' => strip_tags($nodeContent),
                            'order' => $p,
                        ]);
                    }
                } elseif (EntityType::tryFrom($type) === EntityType::AUDIO) {
                    // Create 5 "Segments" for Audio to test Playlist
                    for ($s = 1; $s <= 5; $s++) {
                        $startTime = ($s - 1) * 60; // 0, 60, 120...
                        $nodeTitle = 'المقطع ' . $s . ' ' . ($s === 1 ? '(المقدمة)' : '(شرح)');
                        $nodeId = (string) Str::uuid();
                        $startTime = ($s - 1) * 60; // 0, 60, 120...
                        $nodeContent = "<p>تفريغ نصي للمقطع رقم {$s} يبدأ عند الدقيقة " . ($s-1) . "...</p>";

                        $contentService->createNode($entity, [
                            '_id' => $nodeId,
                            'type' => ContentNodeType::SEGMENT->value,
                            'title' => $nodeTitle,
                            'slug' => 'segment-' . $s . '-' . mb_substr($entity->slug, 0, 4),
                            'content' => $nodeContent,
                            'json_content' => $this->generateJsonContent($nodeContent),
                            'plain_text' => strip_tags($nodeContent),
                            'order' => $s,
                            'start_time' => (float) $startTime,
                            'end_time' => (float) ($startTime + 50), // 50s duration
                        ]);
                    }
                } elseif (EntityType::tryFrom($type) === EntityType::VIDEO) {
                    // Create 3 "Scenes" for Video
                    for ($s = 1; $s <= 3; $s++) {
                        $startTime = ($s - 1) * 120; // 0, 120, 240...
                        $nodeTitle = 'المشهد ' . $s;
                        $nodeId = (string) Str::uuid();
                        $startTime = ($s - 1) * 120; // 0, 120, 240...
                        $nodeContent = "<p>وصف تفصيلي للمشهد رقم {$s}...</p>";

                        $contentService->createNode($entity, [
                            '_id' => $nodeId,
                            'type' => ContentNodeType::SCENE->value,
                            'title' => $nodeTitle,
                            'slug' => 'scene-' . $s . '-' . mb_substr($entity->slug, 0, 4),
                            'content' => $nodeContent,
                            'json_content' => $this->generateJsonContent($nodeContent),
                            'plain_text' => strip_tags($nodeContent),
                            'order' => $s,
                            'start_time' => (float) $startTime,
                            'end_time' => (float) ($startTime + 100),
                        ]);
                    }
                }
                // --- END MongoDB Seeding ---

                // 6. Ecosystem Logic (Polymorphic Authors & Versions)

                // Attach Specific or Random Authors
                $authorName = $itemData['author'];
                $assignedAuthor = Author::query()->where('name', $authorName)->first();
                if ($assignedAuthor) {
                    $entity->authors()->attach($assignedAuthor->id);
                } else {
                    $entity->authors()->attach($authors->random(rand(1, 2))->pluck('id'));
                }

                // Attach Contributors (Bookers) for Books and Manuscripts
                if (EntityType::tryFrom($type)?->supportsPages() && rand(1, 10) > 4) {
                    $role = (EntityType::tryFrom($type) === EntityType::BOOK) ? 'editor' : 'illustrator';
                    $entity->bookers()->attach($bookers->random()->id, ['role' => $role]);
                }

                // Create Versions for each entity (Manuscripts: 4, Others: 3)
                $versionCount = (EntityType::tryFrom($type) === EntityType::MANUSCRIPT) ? 4 : 3;
                for ($v = 1; $v <= $versionCount; $v++) {
                    $vTitle = match ($type) {
                        'audio', 'video' => "تسجيل {$v}",
                        'book' => "الطبعة {$v}",
                        'manuscript' => "النسخة " . match ($v) { 1 => 'أ', 2 => 'ب', 3 => 'ج', 4 => 'د', default => $v},
                    };

                    Version::query()->create([
                        'versionable_id' => $entity->id,
                        'versionable_type' => $type, // Matches morphMap
                        'publisher_id' => $publishers->random()->id,
                        'title' => $vTitle,
                        'isbn' => (EntityType::tryFrom($type) === EntityType::BOOK) ? Str::random(13) : null,
                        'pages' => EntityType::tryFrom($type)?->supportsPages() ? rand(100, 1000) : null,
                        'published_year' => rand(1900, 2024),
                        'edition_number' => $v,
                        'format' => match ($type) {
                            'book', 'manuscript' => 'pdf',
                            'audio' => 'mp3',
                            'video' => 'mp4',
                        },
                        'file_path' => $entity->file_path ?? null,
                        'shelf_id' => (rand(1, 10) > 3) ? Shelf::query()->inRandomOrder()->first()?->id : null,
                    ]);
                }

                // Relationships (Every entity MUST have these)
                $entity->categories()->attach($categories->random(1)->pluck('id'));
                $entity->tags()->attach($tags->random(rand(2, 4))->pluck('id'));
                if (EntityType::tryFrom($type) === EntityType::BOOK) {
                    $entity->topics()->attach($topics->random(rand(1, 2))->pluck('id'));
                }

                // Interactions (Random but frequent)
                if (rand(1, 10) > 2) {
                    Comment::query()->create([
                        'user_id' => $users->random()->id,
                        'entity_id' => $entity->id,
                        'entity_type' => $type,
                        'content' => "تعليق ثري على {$title}، أنصح الجميع بالاطلاع عليه."
                    ]);
                }

                if (rand(1, 10) > 4) {
                    Note::query()->create([
                        'user_id' => $users->random()->id,
                        'entity_id' => $entity->id,
                        'entity_type' => $type,
                        'content' => "ملاحظة علمية هامة تتعلق بالمحتوى الموجود في {$title}."
                    ]);
                }

                Activity::query()->create([
                    'user_id' => $users->random()->id,
                    'entity_id' => $entity->id,
                    'entity_type' => $type,
                    'activity_type' => 'viewed',
                    'description' => "استعرض المستخدم {$title}"
                ]);

                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        // 6. Seeding Collections & Series (Overall grouping)
        $this->info("Creating Collections and Series...");

        $collectionTitles = ['مجموعتي المفضلة', 'مراجعات أدبية', 'كنوز تراثية'];
        foreach ($collectionTitles as $name) {
            $col = Collection::query()->create([
                'name' => $name,
                'user_id' => $users->random()->id,
                'description' => "مجموعة تضم مختارات من $name",
                'is_public' => true
            ]);

            // Add 3-5 random entities to each collection
            $allEntities->random(min(rand(3, 5), $allEntities->count()))->each(fn($e) => $col->addEntity($e));
        }

        $seriesTitles = ['سلسلة تاريخ الأندلس', 'روائع الأدب العربي'];
        foreach ($seriesTitles as $i => $title) {
            $series = Series::query()->create([
                'title' => $title,
                'description' => "سلسلة مرتبة لـ $title",
                'order_column' => $i + 1
            ]);

            // Add 3 random entities to each series
            $allEntities->random(min(3, $allEntities->count()))->each(fn($e, $idx) => $series->addEntity($e, $idx + 1));
        }

        $this->info("Seeding completed successfully with all relationships!");
    }

    /**
     * Generate a basic Tiptap JSON structure for seeding
     */
    protected function generateJsonContent($contentHtml)
    {
        $text = strip_tags($contentHtml);
        
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'attrs' => ['textAlign' => 'right'],
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $text
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Helper to download sample files if they don't exist
     */
    protected function ensureFileExists($url, $path)
    {
        $fullPath = storage_path('app/public/' . $path);

        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (!file_exists($fullPath)) {
            $this->info("Downloading sample file: $path ...");

            // Use stream context to avoid some rudimentary blockers or timeouts
            $arrContextOptions = array(
                "ssl" => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ),
            );

            $content = @file_get_contents($url, false, stream_context_create($arrContextOptions));

            if ($content) {
                file_put_contents($fullPath, $content);
                $this->info("Downloaded: $path");
            } else {
                $this->warn("Failed to download: $url");
            }
        }
    }

    /**
     * Search for a local file matching the title in the given directory.
     * Returns the file path if found, null otherwise.
     */
    protected function searchLocalFile(string $dir, string $title, string $fallback): ?string
    {
        $storage = \Illuminate\Support\Facades\Storage::disk('public');
        
        if (!$storage->exists($dir)) {
            return null;
        }

        // Get ALL files recursively (including bundles/subdirectories)
        $files = $storage->allFiles($dir);
        
        // Sanitize title for search (remove special chars, simple normalization)
        $searchTerms = explode(' ', Str::limit($title, 30, '')); // Search by first few words
        
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_BASENAME);
            
            // 1. Direct match (e.g. "Title.pdf" or "مقدمة_ابن_خلدون.pdf")
            if (Str::contains(strtolower($filename), strtolower($title))) {
                return $file;
            }
            
            // 2. Fuzzy match (e.g. contains significant part of title)
            foreach ($searchTerms as $term) {
                 if (mb_strlen($term) > 3 && Str::contains(strtolower($filename), strtolower($term))) {
                     return $file;
                 }
            }
        }

        return null; // Not found
    }
}
