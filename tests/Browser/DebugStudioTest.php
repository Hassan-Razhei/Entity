<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Audio;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class DebugStudioTest extends DuskTestCase
{
    public function test_inspect_studio_view()
    {
        $this->browse(function (Browser $browser) {
            $user  = User::first();
            // استخدام أي audio موجود بدلاً من slug ثابت قد يتغير بعد كل seed
            $audio = Audio::whereHas('nodes')->first() ?? Audio::first();

            if (!$user || !$audio) {
                $this->markTestSkipped('لا توجد بيانات كافية — شغّل: php artisan project:seed-realistic');
            }

            $browser->loginAs($user)
                ->visit(route('studio.show', ['type' => 'audio', 'slug' => $audio->slug]))
                ->waitFor('.tiptap-editor', 15)
                ->pause(2000)
                ->screenshot('debug_user_studio_view');
                
            $html = $browser->script("return document.querySelector('.ProseMirror')?.innerHTML;")[0];
            echo "\n--- PROSEMIRROR HTML ---\n" . substr($html, 0, 1000) . "\n--- END ---\n";
            
            $playlistItems = $browser->script("return Array.from(document.querySelectorAll('.item')).map(el => el.textContent.trim());")[0];
            echo "\n--- PLAYLIST ITEMS ---\n" . print_r($playlistItems, true) . "\n";
        });
    }
}
