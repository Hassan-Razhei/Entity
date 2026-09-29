<?php

namespace App\Console\Commands;

use App\Models\ContentNode;
use App\Helpers\SlugHelper;
use Illuminate\Console\Command;

class RegenerateContentSlugs extends Command
{
    protected $signature = 'content:regenerate-slugs';
    protected $description = 'Regenerate slugs for all content nodes using SlugHelper on PostgreSQL';

    public function handle()
    {
        $this->info('Starting slug regeneration for ContentNodes on PostgreSQL...');

        $count = 0;

        ContentNode::chunk(100, function ($items) use (&$count) {
            foreach ($items as $item) {
                if ($item->title) {
                    $newSlug = SlugHelper::arabicSlug($item->title);
                    
                    // Ensure uniqueness
                    $originalSlug = $newSlug;
                    $counter = 1;
                    while (ContentNode::where('slug', $newSlug)->where('id', '!=', $item->id)->exists()) {
                        $newSlug = $originalSlug . '-' . $counter;
                        $counter++;
                    }

                    $item->slug = $newSlug;
                    $item->save();
                    $count++;
                }
            }
        });

        $this->info("✓ Regenerated {$count} ContentNode slugs successfully!");
        return 0;
    }
}
