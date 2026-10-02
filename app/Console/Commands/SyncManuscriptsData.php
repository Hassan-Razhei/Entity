<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Models\Author;
use App\Models\Category;
use App\Models\Manuscript;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Str;

class SyncManuscriptsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "manuscriptsData:sync {file? : The path to the source file} {--source= : The source name} {--dry-run : Preview changes}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync/Import legacy manuscript data from CSV/Excel/JSON files into the new schema (file path required)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $source = $this->option('source');
        $isDryRun = $this->option('dry-run');

        if (!$filePath) {
            $this->error("Please provide a file path.");
            return 1;
        }

        if (!File::exists($filePath)) {
            $this->error("File not found: $filePath");
            return 1;
        }

        $extension = strtolower(File::extension($filePath));
        $this->info("Processing file: $filePath ($extension)");

        if ($isDryRun) {
            $this->warn("⚠ DRY RUN MODE: No changes will be saved to the database.");
        }

        $rows = $this->loadData($filePath, $extension);

        if (empty($rows)) {
            $this->warn("No data found in file.");
            return 0;
        }

        $this->info("Found " . count($rows) . " rows. Starting sync...");

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $lastManuscriptId = null;

        foreach ($rows as $index => $row) {
            try {
                $manuscript = $this->processRow($row, $index, $isDryRun);
                if ($manuscript && isset($manuscript->id)) {
                    $stats['imported']++;
                    $lastManuscriptId = $manuscript->id;
                } else {
                    // processRow أرجعت null = صف متجاهل (missing title أو dry-run)
                    $stats['skipped']++;
                }
            } catch (\Exception $e) {
                $this->error("Error at row $index: " . $e->getMessage());
                $stats['errors']++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($stats['imported'] > 0 && !$isDryRun) {
            Activity::create([
                'activity_type' => 'synced',
                'description' => "مزامنة " . $stats['imported'] . " مخطوط من الملف: " . basename($filePath),
                'user_id' => \Illuminate\Support\Facades\Auth::id() ?? User::first()?->id,
                'entity_id' => $lastManuscriptId,
                'entity_type' => Manuscript::class,
            ]);
        }

        $this->table(['Metric', 'Count'], [
            ['Imported', $stats['imported']],
            ['Skipped', $stats['skipped']],
            ['Errors', $stats['errors']],
        ]);
        
        return 0;
    }

    protected function loadData($filePath, $extension)
    {
        if (in_array($extension, ['csv', 'xlsx', 'ods'])) {
            // Check for Spatie Simple Excel
            if (!class_exists(\Spatie\SimpleExcel\SimpleExcelReader::class)) {
                $this->error("Spatie Simple Excel is required for CSV/Excel/ODS. Please run: composer require spatie/simple-excel");
                // Fallback for CSV if simple-excel is missing? 
                if ($extension === 'csv') {
                    return $this->loadCsvNative($filePath);
                }
                throw new \Exception("Spatie Simple Excel is required for CSV/Excel/ODS. Please run: composer require spatie/simple-excel");
            }
            
            // Use Spatie Reader
            $rows = [];
            \Spatie\SimpleExcel\SimpleExcelReader::create($filePath)->getRows()->each(function(array $row) use (&$rows) {
                $rows[] = $row;
            });
            return $rows;
        } elseif ($extension === 'json') {
            $json = json_decode(File::get($filePath), true);
            return $json ?? [];
        } elseif ($extension === 'xml') {
            $this->error("XML support not implemented yet.");
            return [];
        }

        $this->error("Unsupported file extension: $extension");
        return [];
    }

    protected function loadCsvNative($filePath)
    {
        $rows = [];
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($header) === count($data)) {
                     $rows[] = array_combine($header, $data);
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    protected function processRow($row, $index, $isDryRun)
    {
        // 1. Validate Mandatory Fields
        $title = $row['اسم المخطوط'] ?? $row['العنوان'] ?? null;
        if (!$title) {
            $this->warn("Row $index: Missing Title. Skipped.");
            return;
        }

        // 2. Author Handling
        $authorName = $row['اسم المؤلف'] ?? null;
        $author = null;
        if ($authorName && $authorName !== 'مجهول' && $authorName !== 'لا يوجد') {
            $authorData = [
                'madhab' => $row['المذهب'] ?? null,
                'region' => $row['البلد'] ?? null,
            ];
            $author = $this->findOrCreateAuthor($authorName, $authorData, $isDryRun);
        }

        // 3. Category Handling
        $categoryName = $row['موضوع المخطوط'] ?? 'غير مصنف';
        $category = $this->findOrCreateCategory($categoryName, $isDryRun);

        // 4. Manuscript Data Mapping
        $catalogNumber = $row['رقم المخطوط'] ?? null;
        $copyDate = $row['تاريخ النسخ'] ?? null;
        $scribe = $this->cleanValue($row['اسم الناسخ']);
        $parts = $this->cleanValue($row['الأجزاء']);
        $description = $row['المحتوى'] ?? '';
        $notes = $this->cleanValue($row['ملاحظات'] ?? '');
        $inscriptions = $this->cleanValue($row['الفوائد'] ?? '');
        
        // Metadata Parsing
        $centuryData = $this->parseCentury($copyDate);
        $isAutograph = $this->checkAutograph($description . ' ' . $notes);

        if ($isDryRun) {
            $this->info("   [DRY RUN] Would create manuscript: $title ($catalogNumber)");
            return;
        }

        // 5. Create/Update Manuscript
        // catalog_number: مفتاح البحث الأساسي
        // إذا كان null نستخدم العنوان + المؤلف بدلاً منه لتجنب تلويث
        // أول مخطوط بدون رقم فهرسة (كان يحدث في MongoDB بشكل صامت)
        if ($catalogNumber) {
            $searchKey = ['catalog_number' => $catalogNumber];
        } else {
            $searchKey = ['title' => $title, 'original_title' => $title];
        }

        $manuscript = Manuscript::updateOrCreate(
            $searchKey,
            [
                'title'                    => $title,
                'original_title'           => $title,
                'catalog_number'           => $catalogNumber,
                'copy_date'                => $copyDate,
                'scribe'                   => $scribe,
                'parts'                    => $parts,
                'manuscript_century'       => $centuryData['century'],
                'manuscript_century_label' => $centuryData['label'],
                'is_autograph'             => $isAutograph,
                'description'              => $description,
                'notes'                    => $notes,
                'inscriptions'             => $inscriptions,
            ]
        );

        if ($category) {
            $manuscript->categories()->syncWithoutDetaching([$category->id]);
        }

        if ($author && $author->id !== 0) {
            $manuscript->authors()->syncWithoutDetaching([$author->id]);
        }
        
        // 6. Content / Pages Parsing
        if (!empty($description)) {
             $pages = $this->parsePages($description);
             if (count($pages) > 1) {
                 // We found page markers! Sync them to MongoDB
                 if (!$isDryRun) {
                     $this->syncManuscriptPages($manuscript, $pages);
                 } else {
                     $this->info("   [DRY RUN] Would create " . count($pages) . " pages from description.");
                 }
             }
        }
        return $manuscript;
    }

    protected function findOrCreateAuthor($name, $data, $isDryRun)
    {
        if ($isDryRun) return (object) ['id' => 0, 'slug' => 'dry-run'];

        // Normalize Name (Simple)
        $name = trim($name);
        
        $author = Author::where('name', 'LIKE', $name)->first();

        if (!$author) {
            $author = Author::create([
                'name' => $name,
                'slug' => Str::slug($name, '-', null),
                'madhab' => $data['madhab'],
                'original_region' => $data['region'],
            ]);
            $this->info("   + Created Author: $name");
        } else {
            // Enrich optional fields if missing
            $updated = false;
            if (!$author->madhab && !empty($data['madhab'])) {
                $author->madhab = $data['madhab'];
                $updated = true;
            }
            if (!$author->original_region && !empty($data['region'])) {
                $author->original_region = $data['region'];
                $updated = true;
            }
            if ($updated) $author->save();
        }

        return $author;
    }

    protected function findOrCreateCategory($name, $isDryRun)
    {
        if ($isDryRun) return (object) ['id' => 0];
        
        return Category::firstOrCreate(
            ['name' => trim($name)],
            ['slug' => Str::slug($name, '-', null)]
        );
    }

    protected function cleanValue($value)
    {
        if (!$value) return null;
        $value = trim($value);
        if (in_array($value, ['لا يوجد', '(-)', '-', 'غير محدد'])) return null;
        return $value;
    }

    protected function parseCentury($dateString)
    {
        if (!$dateString) return ['century' => null, 'label' => null];
        
        // Extract 3 or 4 digits (Hijri Year)
        if (preg_match('/(\d{3,4})/', $dateString, $matches)) {
            $year = (int) $matches[1];
            $century = ceil($year / 100);
            return [
                'century' => (string) $century,
                'label' => "القرن " . $this->numberToOrdinal($century)
            ];
        }
        return ['century' => null, 'label' => null];
    }

    protected function numberToOrdinal($n)
    {
        $ordinals = [
             1 => 'الأول', 2 => 'الثاني', 3 => 'الثالث', 4 => 'الرابع', 5 => 'الخامس',
             6 => 'السادس', 7 => 'السابع', 8 => 'الثامن', 9 => 'التاسع', 10 => 'العاشر',
             11 => 'الحادي عشر', 12 => 'الثاني عشر', 13 => 'الثالث عشر', 14 => 'الرابع عشر', 15 => 'الخامس عشر'
        ];
        return $ordinals[$n] ?? $n;
    }

    protected function checkAutograph($text)
    {
        $keywords = ['بخط المؤلف', 'وعليه خطه', 'بخط المصنف', 'Autograph', 'مسودة المؤلف'];
        foreach ($keywords as $keyword) {
            if (Str::contains($text, $keyword)) return true;
        }
        return false;
    }

    protected function parsePages($text)
    {
        $lines = explode("\n", $text);
        $parsed = [];
        $currentPage = null;

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Detect Page Markers (Same regex as SyncManuscriptPages)
            if (preg_match('/\[(?:ص|صفحة)\s*(\d+)\]/u', $line, $matches) || preg_match('/^-+\s*(?:Page|صفحة)\s*(\d+)\s*-+$/ui', $line, $matches)) {
                 $pageNumber = (int) $matches[1];
                 
                 if ($currentPage) {
                     $parsed[] = $currentPage;
                 }
                 
                 $currentPage = [
                     'number' => $pageNumber,
                     'content' => ''
                 ];
            } else {
                 if ($currentPage) {
                     $currentPage['content'] .= $line . "\n";
                 } elseif (!empty($line) && empty($parsed)) {
                     // Content before first page marker? Treat as Page 1 or Intro
                     $currentPage = ['number' => 1, 'content' => $line . "\n"];
                 }
            }
        }
        if ($currentPage) $parsed[] = $currentPage;
        return $parsed;
    }

    protected function syncManuscriptPages($manuscript, $pages)
    {
        $contentService = app(\App\Services\EntityContentService::class);
        $startOrder     = (int) $contentService->getMaxOrder($manuscript) + 1;
        $nodeType       = 'page';

        foreach ($pages as $index => $page) {
            $title   = "صفحة {$page['number']}";
            $content = trim($page['content']);

            // التحقق من التكرار — page_number في metadata JSONB (ليس عمود مباشر)
            $exists = $manuscript->children()
                ->whereRaw("(metadata->>'page_number')::int = ?", [$page['number']])
                ->exists();

            if ($exists) {
                continue; // تجاوز التكرار بصمت
            }

            // أنشئ النود أولاً للحصول على ID حقيقي (نفس نمط SyncManuscriptPages)
            $node = $contentService->createNode($manuscript, [
                'type'        => $nodeType,
                'title'       => $title,
                'slug'        => "page-{$page['number']}-" . \Illuminate\Support\Str::random(6),
                'content'     => '',
                'json_content' => [],
                'plain_text'  => strip_tags($content),
                'page_number' => $page['number'],   // يصل لـ metadata عبر EntityContentService
                'order'       => $startOrder + $index,
            ]);

            // استخدام ID الحقيقي من قاعدة البيانات وليس UUID عشوائي
            $nodeId      = $node->id;
            $jsonContent = $this->generateJsonContent($title, $content, $nodeId);

            // كل سطر في فقرة منفصلة (كما كان في MongoDB)
            $paragraphs  = array_filter(array_map('trim', explode("\n", $content)));
            $contentHtml = implode('', array_map(fn($l) => "<p>{$l}</p>", $paragraphs));
            if (empty($contentHtml)) $contentHtml = '<p></p>';

            $headerHtml = "<h4 class=\"structure-marker\" data-segment-link=\"true\" "
                        . "data-id=\"{$nodeId}\" data-type=\"{$nodeType}\">{$title}</h4>";

            $node->update([
                'content_html' => $headerHtml . $contentHtml,
                'content_json' => $jsonContent,
            ]);
        }

        $this->info("   + Synced " . count($pages) . " pages to PostgreSQL.");
    }

   protected function generateJsonContent($title, $rawContent, $nodeId)
    {
        $contentNodes = [];

        // 1. Add Heading Marker
        $contentNodes[] = [
            'type' => 'heading',
            'attrs' => [
                'level' => 4,
                'textAlign' => 'right',
                'class' => 'structure-marker',
                'id' => null,
                'data-segment-link' => 'true',
                'data-id' => $nodeId,
                'data-type' => 'page'
            ],
            'content' => [
                 [
                    'type' => 'text',
                    'marks' => [
                        [
                            'type' => 'segmentLink',
                            'attrs' => [
                                'link' => 'true',
                                'json' => $nodeId
                            ]
                        ]
                    ],
                    'text' => $title
                 ]
            ]
        ];

        // 2. Add Content paragraphs
        $lines = explode("\n", trim($rawContent));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $contentNodes[] = [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'right'],
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $line
                    ]
                ]
            ];
        }

        return [
            'type' => 'doc',
            'content' => $contentNodes
        ];
    }
}
