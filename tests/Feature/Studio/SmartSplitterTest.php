<?php

namespace Tests\Feature\Studio;

use App\Models\Audio;
use App\Models\ContentNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmartSplitterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function full_view_aggregates_segments_with_markers(): void
    {
        $user = User::factory()->create();
        $audio = Audio::firstOrCreate(
            ['slug' => 'audio-smart-test'],
            ['title' => 'شرح صوتي اختباري', 'duration' => 3600]
        );
        
        $segment1 = $audio->nodes()->create([
            'slug' => 'test-segment-1',
            'type' => 'segment',
            'title' => 'المقطع الأول',
            'order' => 1,
            'metadata' => ['start_time' => 0.0],
            'content_html' => '<p>محتوى المقطع الأول للاختبار.</p>',
            'plain_text' => 'محتوى المقطع الأول للاختبار.',
        ]);

        $segment2 = $audio->nodes()->create([
            'slug' => 'test-segment-2',
            'type' => 'segment',
            'title' => 'المقطع الثاني',
            'order' => 2,
            'metadata' => ['start_time' => 300.0],
            'content_html' => '<p>محتوى المقطع الثاني للاختبار.</p>',
            'plain_text' => 'محتوى المقطع الثاني للاختبار.',
        ]);
        
        $segments = $audio->children()->orderBy('order')->get();
        
        // Make authenticated request to Full View (no childId = Full View)
        $response = $this->actingAs($user)
            ->get("/studio/audio/{$audio->slug}");
        
        $response->assertStatus(200);
        
        // Verify Inertia props contain aggregated content
        $props = $response->viewData('page')['props'];
        
        $this->assertArrayHasKey('editorContent', $props);
        $aggregatedContent = $props['editorContent'];
        
        // Verify each segment appears with its marker
        foreach ($segments as $segment) {
            $this->assertStringContainsString(
                "<h4 class=\"structure-marker\"",
                $aggregatedContent,
                "Segment '{$segment->title}' marker not found in aggregated content"
            );
            $this->assertStringContainsString(
                $segment->title,
                $aggregatedContent,
                "Segment title not found"
            );
            
            $cleanSegmentContent = strip_tags($segment->content_html);
            $cleanAggregatedContent = strip_tags($aggregatedContent);
            
            $this->assertStringContainsString(
                $cleanSegmentContent,
                $cleanAggregatedContent,
                "Segment '{$segment->title}' content not found in aggregated view"
            );
        }
    }
    
    #[Test]
    public function full_view_save_fragments_to_segments(): void
    {
        $user = User::factory()->create();
        $audio = Audio::firstOrCreate(
            ['slug' => 'audio-split-save-test'],
            ['title' => 'شرح تجزئة الصوت', 'duration' => 3600]
        );
        
        $segment1 = $audio->nodes()->create([
            'slug' => 'segment-1',
            'type' => 'segment',
            'title' => 'المقطع الأول',
            'order' => 1,
            'metadata' => ['start_time' => 0.0],
            'content_html' => '<p>محتوى قديم 1</p>',
        ]);
        
        $segment2 = $audio->nodes()->create([
            'slug' => 'segment-2',
            'type' => 'segment',
            'title' => 'المقطع الثاني',
            'order' => 2,
            'metadata' => ['start_time' => 300.0],
            'content_html' => '<p>محتوى قديم 2</p>',
        ]);
        
        // Simulate Full View save with new content
        $newFullContent = 
            "<h4 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$segment1->id}\">{$segment1->title}</h4>\n" .
            "<p>محتوى جديد للمقطع الأول بعد التعديل</p>\n" .
            "<h4 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$segment2->id}\">{$segment2->title}</h4>\n" .
            "<p>محتوى جديد للمقطع الثاني بعد التعديل</p>";
        
        // Make POST request to save endpoint with child_id='full'
        $response = $this->actingAs($user)
            ->post("/studio/audio/{$audio->slug}/full/save", [
                'content' => $newFullContent,
                'child_id' => 'full'
            ]);
        
        $response->assertStatus(200);
        
        // Verify segments were updated correctly
        $newSegment1 = ContentNode::find($segment1->id);
        $newSegment2 = ContentNode::find($segment2->id);
        
        $this->assertNotNull($newSegment1);
        $this->assertNotNull($newSegment2);
        
        $this->assertStringContainsString(
            'محتوى جديد للمقطع الأول',
            $newSegment1->content_html,
            'Segment 1 content was not updated correctly'
        );
        
        $this->assertStringContainsString(
            'محتوى جديد للمقطع الثاني',
            $newSegment2->content_html,
            'Segment 2 content was not updated correctly'
        );
        
        // Verify markers were removed from individual segments
        $this->assertStringNotContainsString(
            '<h4',
            $newSegment1->content_html,
            'Segment 1 should not contain marker tags'
        );
        
        $this->assertStringNotContainsString(
            '<h4',
            $newSegment2->content_html,
            'Segment 2 should not contain marker tags'
        );
    }
    
    #[Test]
    public function segment_links_preserved_during_split(): void
    {
        $user = User::factory()->create();
        $audio = Audio::firstOrCreate(
            ['slug' => 'audio-segment-link-test'],
            ['title' => 'شرح روابط المقاطع', 'duration' => 3600]
        );
        
        $segment1 = $audio->nodes()->create([
            'slug' => 'segment-link-1',
            'type' => 'segment',
            'title' => 'المقطع الأول',
            'order' => 1,
            'metadata' => ['start_time' => 0.0],
            'content_html' => '<p>محتوى عادي</p>',
        ]);
        
        // Full content with SegmentLink marker (UUID id)
        $newFullContent = 
            "<h4 class=\"structure-marker\" data-segment-link=\"true\" data-id=\"{$segment1->id}\">{$segment1->title}</h4>\n" .
            '<p>نص يحتوي على <span data-segment-link data-id="' . $segment1->id . '" data-start-time="0" class="segment-link">رابط مقطع</span> داخله.</p>';
        
        $response = $this->actingAs($user)
            ->post("/studio/audio/{$audio->slug}/full/save", [
                'content' => $newFullContent,
                'child_id' => 'full'
            ]);
        
        $response->assertStatus(200);
        
        $newSegment1 = ContentNode::find($segment1->id);
        $this->assertNotNull($newSegment1);
        
        // Verify SegmentLink attributes are preserved
        $this->assertStringContainsString('data-segment-link', $newSegment1->content_html);
        $this->assertStringContainsString('data-id', $newSegment1->content_html);
        $this->assertStringContainsString('data-start-time', $newSegment1->content_html);
        $this->assertStringContainsString('segment-link', $newSegment1->content_html);
    }
}
