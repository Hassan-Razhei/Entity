<?php

namespace App\Console\Commands;

use App\Enums\EntityType;
use App\Enums\ContentNodeType;
use App\Models\Manuscript;
use App\Services\EntityContentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class SyncManuscriptPages extends Command
{
    protected $signature = 'manuscript:sync {path? : Path to folder containing docx files} {--dry-run : Preview pages without saving}';
    protected $description = 'Parse docx files and create manuscript pages automatically';

    protected $contentService;

    public function __construct(EntityContentService $contentService)
    {
        parent::__construct();
        $this->contentService = $contentService;
    }

    public function handle()
    {
        $inputPath = $this->argument('path');

        // If no path provided, try default but warn
        if (!$inputPath) {
            $defaultPath = storage_path('app/manuscripts');
            if (!File::exists($defaultPath)) {
                $this->error("No path provided and default directory '$defaultPath' does not exist.");
                return 1;
            }
            $inputPath = $defaultPath;
        }

        $files = [];

        if (File::isDirectory($inputPath)) {
            $this->info("Scanning directory: $inputPath");
            $files = File::files($inputPath);
        } elseif (File::isFile($inputPath)) {
            $this->info("Processing single file: $inputPath");
            $files = [new \SplFileInfo($inputPath)];
        } else {
            $this->error("Invalid path: $inputPath");
            return 1;
        }

        $count = 0;
        $totalCreated = 0;
        $totalSkipped = 0;
        
        foreach ($files as $file) {
            if ($file->getExtension() !== 'docx')
                continue;

            $this->info("Processing: " . $file->getFilename());
            $stats = $this->processFile($file);
            $totalCreated += $stats['created'] ?? 0;
            $totalSkipped += $stats['skipped'] ?? 0;
            $count++;
        }

        if ($count === 0) {
            $this->warn("No .docx files found in the specified path.");
        } else {
            $this->newLine();
            $this->info("═══════════════════════════════════════");
            $this->info("  Sync Complete!");
            $this->info("═══════════════════════════════════════");
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Files Processed', $count],
                    ['Pages Created', $totalCreated],
                    ['Duplicates Skipped', $totalSkipped],
                ]
            );
            
            if ($this->option('dry-run')) {
                $this->warn("⚠ DRY RUN MODE - No changes were saved to database");
            }
        }
    }

    protected function processFile($file)
    {
        $text = $this->readDocx($file->getPathname());
        if (!$text) {
            $this->warn("   - Could not read text from file.");
            return ['created' => 0, 'skipped' => 0];
        }

        // 1. Find Manuscripts matching the filename
        $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);

        $manuscripts = $this->findManuscripts($filename);

        if ($manuscripts->isEmpty()) {
            $this->warn("   - No matching manuscripts found for '$filename'");
            return ['created' => 0, 'skipped' => 0];
        }

        $this->info("   - Found " . $manuscripts->count() . " matching manuscript(s).");

        // 2. Parse Pages
        $pages = $this->parsePages($text);

        if (empty($pages)) {
            $this->warn("   - No pages found in text.");
            return ['created' => 0, 'skipped' => 0];
        }

        $this->info("   - Parsed " . count($pages) . " page(s).");

        // 3. Store Pages for ALL matches (or preview if dry-run)
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn("   [DRY RUN MODE] - No changes will be saved");
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($manuscripts as $manuscript) {
            $this->info("   > " . ($isDryRun ? "Would attach to" : "Attaching to") . ": {$manuscript->title} (ID: {$manuscript->id})");
            $stats = $this->storePages($manuscript, $pages, $isDryRun);
            $totalCreated += $stats['created'];
            $totalSkipped += $stats['skipped'];
        }
        
        return ['created' => $totalCreated, 'skipped' => $totalSkipped];
    }

    protected function findManuscripts($filename)
    {
        return Manuscript::where('title', 'LIKE', "%$filename%")
            ->orWhere('slug', 'LIKE', "%$filename%")
            ->orWhere('original_title', 'LIKE', "%$filename%")
            ->get();
    }

    protected function readDocx($filePath)
    {
        $content = '';
        $zip = new ZipArchive;

        if ($zip->open($filePath) === true) {
            // Find word/document.xml
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $xmlData = $zip->getFromIndex($index);
                $dom = new \DOMDocument;
                $dom->loadXML($xmlData, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $nodes = $dom->getElementsByTagName('p'); // Paragraphs

                foreach ($nodes as $node) {
                    $content .= $node->nodeValue . "\n";
                }
            }
            $zip->close();
        }
        return $content;
    }

    protected function parsePages($text)
    {
        $lines = explode("\n", $text);
        $parsed = [];
        $currentPage = null;

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Detect Page Markers
            // Pattern 1: [ص1], [ص 2], [صفحة 3]
            // Pattern 2: --- Page 1 ---
            // Pattern 3: Standalone number (risky, use with caution)
            
            if (preg_match('/\[(?:ص|صفحة)\s*(\d+)\]/u', $line, $matches)) {
                // Arabic page marker
                $pageNumber = (int) $matches[1];
                
                // Save previous page
                if ($currentPage) {
                    $parsed[] = $currentPage;
                }
                
                // Start new page
                $currentPage = [
                    'number' => $pageNumber,
                    'content' => ''
                ];
            } elseif (preg_match('/^-+\s*(?:Page|صفحة)\s*(\d+)\s*-+$/ui', $line, $matches)) {
                // Dashed page marker
                $pageNumber = (int) $matches[1];
                
                if ($currentPage) {
                    $parsed[] = $currentPage;
                }
                
                $currentPage = [
                    'number' => $pageNumber,
                    'content' => ''
                ];
            } else {
                // Content line
                if ($currentPage && !empty($line)) {
                    $currentPage['content'] .= $line . "\n";
                }
            }
        }

        // Add last page
        if ($currentPage) {
            $parsed[] = $currentPage;
        }

        return $parsed;
    }

    protected function storePages($manuscript, $pages, $isDryRun = false)
    {
        $nodeType = ContentNodeType::PAGE->value;
        $startOrder = $this->contentService->getMaxOrder($manuscript) + 1;
        
        $created = 0;
        $skipped = 0;

        foreach ($pages as $index => $page) {
            // Check for duplicate
            // page_number مخزّنة في metadata JSONB (PostgreSQL) وليس عمود مباشر
            $exists = $manuscript->children()
                ->whereRaw("(metadata->>'page_number')::int = ?", [$page['number']])
                ->exists();
            
            if ($exists) {
                $this->warn("      ⚠ Skipped (duplicate): Page {$page['number']}");
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $this->line("      + [PREVIEW] Page {$page['number']}");
                $created++;
                continue;
            }

            // Create Node (get ID first)
            $node = $this->contentService->createNode($manuscript, [
                'type' => $nodeType,
                'title' => "صفحة {$page['number']}",
                'slug' => "page-{$page['number']}-" . Str::random(6),
                'content' => '',
                'json_content' => [],
                'plain_text' => trim(strip_tags($page['content'])),
                'page_number' => $page['number'],
                'order' => $startOrder + $index
            ]);

            // Construct Constitutional HTML
            $nodeId = $node->id;
            $title = "صفحة {$page['number']}";

            $headerHtml = "<h4 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$nodeId}\" data-type=\"{$nodeType}\">{$title}</h4>";

            // كل سطر في فقرة منفصلة (كما كان في MongoDB حيث كل فقرة document منفصل)
            $paragraphs  = array_filter(array_map('trim', explode("\n", $page['content'])));
            $contentHtml = implode('', array_map(fn($line) => "<p>{$line}</p>", $paragraphs));
            if (empty($contentHtml)) $contentHtml = '<p></p>';
            
            $fullHtml = $headerHtml . $contentHtml;
            $jsonContent = $this->generateJsonContent($title, $page['content'], $nodeId, $nodeType);

            // Update Node
            $node->update([
                'content' => $fullHtml,
                'json_content' => $jsonContent
            ]);

            $this->line("      <fg=green>✓</> Created: Page {$page['number']}");
            $created++;
        }
        
        // Summary
        if ($created > 0 || $skipped > 0) {
            $this->newLine();
            $this->info("      Summary: " . ($isDryRun ? "Would create" : "Created") . " {$created} page(s)" . ($skipped > 0 ? ", skipped {$skipped} duplicate(s)" : ""));
        }
        
        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Generate Tiptap JSON with Structure Marker
     */
    protected function generateJsonContent($title, $rawContent, $nodeId, $nodeType = 'page')
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
                // Add data attributes for extension hydration
                'data-segment-link' => 'true',
                'data-id' => (string) $nodeId,
                'data-type' => $nodeType
            ],
            'content' => [
                 [
                    'type' => 'text',
                    'marks' => [
                        [
                            'type' => 'segmentLink',
                            'attrs' => [
                                'link' => 'true',
                                'json' => (string) $nodeId
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
