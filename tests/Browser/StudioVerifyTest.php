<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Audio;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class StudioVerifyTest extends DuskTestCase
{
    public function test_verify_studio_in_real_browser()
    {
        $this->browse(function (Browser $browser) {
            $user = User::first();
            $audio = Audio::where('slug', 'شرح-ألفية-ابن-مالك-10')->first();

            $browser->loginAs($user)
                ->visit(route('studio.show', ['type' => 'audio', 'slug' => $audio->slug]))
                ->waitFor('.tiptap-editor', 15)
                ->pause(2000);

            // 1. Open playlist drawer
            $browser->script("
                const btn = document.querySelector('button[title=\"Show Chapters\"]') || document.querySelector('.playlist-toggle');
                if (btn) btn.click();
            ");
            $browser->pause(1000)
                ->screenshot('verified_studio_playlist');

            // 2. Open Add Node Dropdown
            $browser->script("
                const addBtn = document.querySelector('#studio-add-node-btn button') || Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('إضافة'));
                if (addBtn) addBtn.click();
            ");
            $browser->pause(1000)
                ->screenshot('verified_studio_add_dropdown');

            // 3. Select 'مقطع' type
            $browser->script("
                const segmentOpt = Array.from(document.querySelectorAll('button, div')).find(el => el.textContent.trim() === 'مقطع');
                if (segmentOpt) segmentOpt.click();
            ");
            $browser->pause(1000)
                ->screenshot('verified_studio_add_dialog');
        });
    }
}
