<?php

namespace App\Console\Commands;

use App\Enums\EntityType;
use App\Enums\ContentNodeType;
use App\Models\Audio;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncStorage extends Command
{
    protected $signature = 'storage:sync {path? : Optional external path to sync} {--force : Force update metadata for existing items}';
    protected $description = 'Scan storage directory (or external path via symlink) and register new media files';

    public function handle()
    {
        $this->info('Starting storage synchronization...');
        
        $targetPath = $this->argument('path');

        // Default: Scan internal storage
        if (!$targetPath) {
            $this->scanDirectory(null, '');
        } else {
            // External Path Logic
            if (!File::exists($targetPath)) {
                $this->error("External path not found: $targetPath");
                return;
            }

            // 1. Create Symlink Directory if not exists
            $importsDir = storage_path('app/public/imports');
            if (!File::exists($importsDir)) {
                File::makeDirectory($importsDir, 0755, true);
            }

            // 2. Determine Link Name
            $folderName = basename($targetPath);
            $linkPath = "$importsDir/$folderName";

            // 3. Create Symlink if not exists
            if (!is_link($linkPath)) {
                try {
                    symlink($targetPath, $linkPath);
                    $this->info("Created symlink: imports/$folderName -> $targetPath");
                } catch (\Exception $e) {
                    $this->error("Failed to create symlink. Check permissions.");
                    return;
                }
            } else {
                $this->info("Using existing symlink: imports/$folderName");
            }

            // 4. Scan the SYMLINKED path (to get relative web paths)
            $this->scanDirectory($linkPath, "imports/$folderName");
        }

        $this->info('Synchronization completed successfully!');
    }



    protected function scanDirectory($directory, $baseRelativePath)
    {
        if ($directory) {
            $this->info("Scanning directory: $directory");
            $allFiles = File::allFiles($directory);
        } else {
            $this->info("Scanning default storage (public)");
            $allFiles = \Illuminate\Support\Facades\Storage::disk('public')->allFiles();
            // Convert to SplFileInfo-like objects or adapt the loop
        }
        
        $count = 0;

        // Grouping for bundles (Manuscripts)
        $manuscriptFolders = [];

        foreach ($allFiles as $file) {
            if ($file instanceof \Symfony\Component\Finder\SplFileInfo) {
                $ext = strtolower($file->getExtension());
                $fullPath = $file->getPathname();
                $itemFilename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $relativeDir = $file->getRelativePath();
                
                // Calculate relative path for DB/Web
                if ($baseRelativePath) {
                    $relativePathInside = $file->getRelativePathname();
                    $relativePath = $baseRelativePath . '/' . $relativePathInside;
                    $relativePath = str_replace('//', '/', $relativePath);
                } else {
                    $relativePath = $file->getRelativePathname();
                }
            } else {
                // It's a string path (from Storage::allFiles)
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($file);
                $itemFilename = pathinfo($file, PATHINFO_FILENAME);
                $relativeDir = dirname($file);
                $relativePath = $file;
            }

            $pathParts = explode('/', $relativeDir); // Directories as tags
            $media = null;

            // Extensions
            $audioExts = ['mp3', 'm4a', 'wav', 'ogg'];
            $videoExts = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
            $bookExts = ['md', 'txt', 'pdf'];
            $imageExts = ['jpg', 'jpeg', 'png', 'webp'];

            // 1. Audio
            if (in_array($ext, $audioExts)) {
                $media = Audio::firstOrCreate(
                    ['file_path' => $relativePath],
                    [
                        'id' => (string) Str::uuid(),
                        'title' => $this->cleanTitle($itemFilename),
                        'slug' => Str::slug($itemFilename) . '-' . Str::random(6),
                        'type' => EntityType::AUDIO->value,
                        'author_id' => 1,
                        'duration' => 0,
                        'description' => 'Automatically synced from storage.'
                    ]
                );
            }
            // 2. Video
            elseif (in_array($ext, $videoExts)) {
                $media = Video::firstOrCreate(
                    ['file_path' => $relativePath],
                    [
                        'id' => (string) Str::uuid(),
                        'title' => $this->cleanTitle($itemFilename),
                        'slug' => Str::slug($itemFilename) . '-' . Str::random(6),
                        'type' => EntityType::VIDEO->value,
                        'author_id' => 1,
                        'duration' => 0,
                        'description' => 'Automatically synced from storage.'
                    ]
                );
            }
            // 3. Books (Markdown/PDF)
            elseif (in_array($ext, $bookExts)) {
                 $media = \App\Models\Book::where('file_path', $relativePath)
                    ->orWhere('slug', Str::slug($itemFilename))
                    ->first();

                 if ($media) {
                     $media->update([
                         'file_path' => $relativePath,
                         'type' => EntityType::BOOK->value,
                     ]);
                 } else {
                     $media = \App\Models\Book::create([
                        'id' => (string) Str::uuid(),
                        'title' => $this->cleanTitle($itemFilename),
                        'slug' => Str::slug($itemFilename), // Simple slug for specific test case 'sira'
                        'type' => EntityType::BOOK->value,
                        'author_id' => 1,
                        'pages' => 0,
                        'description' => 'Automatically synced from storage.',
                        'file_path' => $relativePath
                    ]);
                 }

                 // If it's a markdown file, sync content
                 if ($ext === 'md') {
                     $this->syncMarkdownContent($media, $fullPath);
                 }
            }
            // 4. Manuscript (Images in Folder) - Collect first, process later or real-time?
            // Test implication: 'manuscripts/Bundle_X/page1.jpg' -> Bundle_X is the Manuscript.
            elseif (in_array($ext, $imageExts) && str_contains($relativePath, 'manuscripts/')) {
                 $parentFolderName = basename($relativeDir);
                 $folderName = ($parentFolderName === 'manuscripts') ? $itemFilename : $parentFolderName;
                 $manuscriptFolders[$folderName][] = [
                     'file' => $file,
                     'relativePath' => $relativePath
                 ];
                 continue; // Skip individual file processing for now
            }

            // Sync Tags & Metadata
            if ($media) {
                 if ($media->wasRecentlyCreated) {
                    $this->line("   + Registered: $relativePath");
                    $count++;
                    
                    // Add tags from directory structure
                    foreach ($pathParts as $part) {
                         if (!empty($part) && !in_array($part, ['audio', 'video', 'books', 'audios', 'videos'])) {
                              $tag = \App\Models\Tag::firstOrCreate(['name' => $part, 'slug' => Str::slug($part)]);
                              $media->tags()->syncWithoutDetaching([$tag->id]);
                         }
                    }

                    // Create initial version
                    $media->versions()->create([
                        'file_path' => $relativePath,
                        'edition_number' => 1,
                        'title' => 'Original',
                        'format' => $ext,
                    ]);
                }
                
                // Audio/Video Metadata Updates... (Existing Logic)
                if ($this->option('force') || (($media instanceof Audio || $media instanceof Video) && ($media->duration === 0 || !$media->bitrate))) {
                     $meta = $this->getMetadata($fullPath);
                     $updates = [];
                     if ($meta['duration'] > 0) $updates['duration'] = $meta['duration'];
                     if ($meta['bitrate'] > 0) $updates['bitrate'] = $meta['bitrate'];
                     if ($meta['format']) $updates['format'] = $meta['format'];
                     
                     if ($this->option('force')) {
                         $updates['description'] = 'Automatically synced from storage.';
                     }
                     
                     if (!empty($updates)) $media->update($updates);
                }
            }
        }

        // Process Manuscripts
        foreach ($manuscriptFolders as $folderName => $pages) {
             $manuscript = \App\Models\Manuscript::firstOrCreate(
                  ['title' => $this->cleanTitle($folderName)],
                  [
                      'id' => (string) Str::uuid(),
                      'slug' => Str::slug($folderName),
                      'type' => EntityType::MANUSCRIPT->value,
                      'pages' => count($pages)
                  ]
             );
             
             // Setup pages
             foreach ($pages as $index => $pageData) {
                  $manuscript->nodes()->firstOrCreate(
                      ['slug' => Str::slug($folderName . '-page-' . ($index + 1))],
                      [
                          'title' => 'Page ' . ($index + 1),
                          'order' => $index + 1,
                          'type' => 'page',
                          'metadata' => ['image_url' => $pageData['relativePath']]
                      ]
                  );
             }
        }

        $this->info("Scan processed $count files.");
    }

    /**
     * Sync Markdown content to Book Children
     */
    protected function syncMarkdownContent($book, $filePath)
    {
        $content = File::get($filePath);
        $parser = new \App\Services\Book\MarkdownStructureParser();
        $structure = $parser->parse($content);
        
        $contentService = app(\App\Services\EntityContentService::class);
        
        foreach ($structure as $index => $node) {
            $existing = $book->nodes()
                ->where('title', $node['title'])
                ->first();
                
            if ($existing && $existing->is_manually_edited) {
                continue;
            }

            $nodeData = [
                'type' => $node['type'],
                'title' => $node['title'],
                'content_blocks' => $node['blocks'],
                'order' => $index + 1
            ];

            if ($existing) {
                $existing->update($nodeData);
            } else {
                $contentService->createNode($book, $nodeData);
            }
        }
    }

    protected function cleanTitle($filename)
    {
        return Str::headline($filename);
    }

    protected function getMetadata($filePath)
    {
        $meta = [
            'duration' => 0,
            'bitrate' => 0,
            'format' => ''
        ];

        try {
            $escapedPath = escapeshellarg($filePath);

            // Get Metadata using ffprobe
            // We ask for specific entries: duration, bit_rate, format_name
            // -v error: hide logs
            // -of default=noprint_wrappers=1:nokey=0: output as key=value pairs
            $cmd = "ffprobe -v error -show_entries format=duration,bit_rate,format_name -of default=noprint_wrappers=1:nokey=0 $escapedPath";
            $output = shell_exec($cmd);

            if ($output) {
                // Parse key=value lines
                $lines = explode("\n", trim($output));
                foreach ($lines as $line) {
                    if (str_contains($line, '=')) {
                        [$key, $val] = explode('=', trim($line), 2);

                        if ($key === 'duration') {
                            $meta['duration'] = (int) floatval($val);
                        } elseif ($key === 'bit_rate') {
                            $meta['bitrate'] = (int) (floatval($val) / 1000); // Convert to kbps
                        } elseif ($key === 'format_name') {
                            // ffprobe returns 'mp3,mp2' sometimes, take first
                            $meta['format'] = explode(',', $val)[0];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // fail silently
        }
        return $meta;
    }
}
