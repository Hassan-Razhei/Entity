<?php

namespace Tests\Browser;

use App\Models\Audio;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class StudioSegmentEditAndSaveVerificationTest extends DuskTestCase
{
    public function test_can_click_segment_edit_and_save_in_chrome()
    {
        $this->browse(function (Browser $browser) {
            $user = User::first();
            $audio = Audio::whereHas('nodes')->first();

            $this->assertNotNull($user, 'User must exist');
            $this->assertNotNull($audio, 'Audio must exist');

            $firstNode = $audio->nodes()->first();
            $this->assertNotNull($firstNode, 'Audio must have at least one content node');

            // 1. Log in and open studio for this audio
            $browser->loginAs($user)
                ->visit(route('studio.show', ['type' => 'audio', 'slug' => $audio->slug]))
                ->waitFor('#studio-save-btn', 15)
                ->pause(2000)
                ->screenshot('step1_studio_initial');

            // 2. Click specific segment dropdown
            $browser->click('#studio-dropdown-btn')
                ->pause(1000)
                ->screenshot('step2_dropdown_opened');

            // 3. Click the specific node item
            $selector = "[dusk=\"node-item-{$firstNode->slug}\"]";
            $fallbackSelector = "[dusk=\"node-item-{$firstNode->id}\"]";

            $browser->script("
                const item = document.querySelector('{$selector}') || document.querySelector('{$fallbackSelector}') || document.querySelector('[dusk^=\"node-item-\"]');
                if (item) {
                    item.click();
                } else {
                    console.error('Node item not found');
                }
            ");
            $browser->pause(2000)->screenshot('step3_segment_selected');

            // 4. Verify EditorStore has currentContentNode loaded with this segment
            $loadedNodeId = $browser->script("
                return window.EditorStore ? (window.EditorStore.currentContentNode?.id || window.EditorStore.currentContentNode?._id) : null;
            ")[0];

            $loadedContent = $browser->script("
                return window.EditorStore ? window.EditorStore.content : null;
            ")[0];

            echo "\n✓ Loaded Node ID in Store: {$loadedNodeId}\n";
            echo "✓ Expected Node ID: {$firstNode->id}\n";
            echo "✓ Content in EditorStore: " . substr($loadedContent ?? '', 0, 50) . "...\n";

            $this->assertEquals($firstNode->id, $loadedNodeId, 'Store must load the selected segment');

            // 5. Modify content via Tiptap editor
            $testText = ' [تعديل تجريبي ناجح ' . time() . ']';
            $browser->script("
                if (window.editor) {
                    window.editor.commands.insertContent('{$testText}');
                } else if (window.EditorStore) {
                    window.EditorStore.updateContent(window.EditorStore.content + '<p>{$testText}</p>');
                }
            ");
            $browser->pause(1000)->screenshot('step4_content_modified');

            // 6. Click Save Button
            $browser->click('#studio-save-btn');

            // Wait for save to complete
            $browser->pause(2500)->screenshot('step5_after_save');

            $statusText = $browser->script("
                return window.EditorStore ? window.EditorStore.lastSaveMessage : null;
            ")[0];

            echo "✓ Save status message: {$statusText}\n";
            $this->assertEquals('تم الحفظ بنجاح', $statusText);

            // 7. Verify in database that firstNode now contains the saved modification
            $firstNode->refresh();
            $this->assertStringContainsString('تعديل تجريبي ناجح', $firstNode->content_html);
            echo "✓ Database verified: Segment content_html successfully updated in PostgreSQL!\n";
        });
    }
}
