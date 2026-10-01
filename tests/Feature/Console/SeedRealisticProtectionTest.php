<?php

namespace Tests\Feature\Console;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * اختبارات نظام حماية أمر seed-realistic
 *
 * يختبر هذا الملف ثلاثة سيناريوهات رئيسية:
 *   1. بيئة التطوير  → يعمل مع تأكيد فقط
 *   2. بيئة الإنتاج → محظور بدون --force + token صحيح
 *   3. التدفق الكامل → يعمل وينشئ البيانات فعلاً
 */
class SeedRealisticProtectionTest extends TestCase
{
    use RefreshDatabase;

    private string $validToken = 'super-secret-test-token-12345';

    protected function tearDown(): void
    {
        // إعادة البيئة إلى local بعد كل اختبار
        $this->app->detectEnvironment(fn() => 'local');
        parent::tearDown();
    }

    // =========================================================================
    // 🟢 سيناريو 1 — بيئة التطوير
    // =========================================================================

    #[Test]
    public function يمكن_تشغيل_الأمر_في_بيئة_التطوير_بعد_التأكيد(): void
    {
        $this->artisan('project:seed-realistic --count=2')
            ->expectsConfirmation(
                '⚠️  سيتم حذف جميع البيانات الحالية وإعادة تعبئتها. هل أنت متأكد؟',
                'yes'
            )
            ->expectsOutputToContain('Starting exhaustive realistic data seeding')
            ->expectsOutputToContain('Seeding completed successfully')
            ->assertExitCode(0);
    }

    #[Test]
    public function يلغي_الأمر_إذا_رفض_المستخدم_التأكيد_في_التطوير(): void
    {
        $this->artisan('project:seed-realistic --count=2')
            ->expectsConfirmation(
                '⚠️  سيتم حذف جميع البيانات الحالية وإعادة تعبئتها. هل أنت متأكد؟',
                'no'
            )
            ->expectsOutput('تم الإلغاء.')
            ->assertExitCode(0);

        // تأكد عدم إنشاء أي بيانات
        $this->assertDatabaseCount('books', 0);
    }

    // =========================================================================
    // 🔴 سيناريو 2 — بيئة الإنتاج: الرفض التام
    // =========================================================================

    #[Test]
    public function يُحظر_الأمر_في_الإنتاج_بدون_force(): void
    {
        $this->app->detectEnvironment(fn() => 'production');

        $this->artisan('project:seed-realistic --count=2')
            ->expectsOutputToContain('هذا الأمر محظور في بيئة الإنتاج')
            ->assertExitCode(1);

        $this->assertDatabaseCount('books', 0);
    }

    #[Test]
    public function يُحظر_الأمر_في_الإنتاج_مع_force_بدون_token(): void
    {
        $this->app->detectEnvironment(fn() => 'production');
        config(['app.seed_secret' => $this->validToken]);

        $this->artisan('project:seed-realistic --force --count=2')
            ->expectsOutputToContain('المفتاح السري غير صحيح')
            ->assertExitCode(1);

        $this->assertDatabaseCount('books', 0);
    }

    #[Test]
    public function يُحظر_الأمر_في_الإنتاج_مع_token_خاطئ(): void
    {
        $this->app->detectEnvironment(fn() => 'production');
        config(['app.seed_secret' => $this->validToken]);

        $this->artisan('project:seed-realistic --force --token=wrong-token-xyz --count=2')
            ->expectsOutputToContain('المفتاح السري غير صحيح')
            ->assertExitCode(1);

        $this->assertDatabaseCount('books', 0);
    }

    #[Test]
    public function يُحظر_الأمر_في_الإنتاج_عند_غياب_SEED_SECRET_من_env(): void
    {
        $this->app->detectEnvironment(fn() => 'production');
        config(['app.seed_secret' => null]);

        $this->artisan('project:seed-realistic --force --token=any-token --count=2')
            ->expectsOutputToContain('SEED_SECRET غير محدد')
            ->assertExitCode(1);

        $this->assertDatabaseCount('books', 0);
    }

    // =========================================================================
    // ✅ سيناريو 3 — الإنتاج مع بيانات اعتماد صحيحة
    // =========================================================================

    #[Test]
    public function يعمل_الأمر_في_الإنتاج_مع_force_و_token_صحيح(): void
    {
        $this->app->detectEnvironment(fn() => 'production');
        config(['app.seed_secret' => $this->validToken]);

        $this->artisan("project:seed-realistic --force --token={$this->validToken} --count=2")
            ->expectsOutputToContain('أنت على وشك حذف جميع بيانات الإنتاج')
            ->expectsConfirmation(
                '⚠️  سيتم حذف جميع البيانات الحالية وإعادة تعبئتها. هل أنت متأكد؟',
                'yes'
            )
            ->expectsOutputToContain('Seeding completed successfully')
            ->assertExitCode(0);

        $this->assertGreaterThan(0, Book::count());
    }

    #[Test]
    public function يُلغى_حتى_في_الإنتاج_إذا_رفض_المستخدم_التأكيد_الأخير(): void
    {
        $this->app->detectEnvironment(fn() => 'production');
        config(['app.seed_secret' => $this->validToken]);

        $this->artisan("project:seed-realistic --force --token={$this->validToken} --count=2")
            ->expectsConfirmation(
                '⚠️  سيتم حذف جميع البيانات الحالية وإعادة تعبئتها. هل أنت متأكد؟',
                'no'
            )
            ->expectsOutput('تم الإلغاء.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('books', 0);
    }

    // =========================================================================
    // 🔒 سيناريو 4 — أمان المقارنة (Timing-Safe)
    // =========================================================================

    #[Test]
    public function يرفض_token_شبه_صحيح_يختلف_بحرف_واحد(): void
    {
        $this->app->detectEnvironment(fn() => 'production');
        config(['app.seed_secret' => $this->validToken]);

        // token يبدأ بنفس الأحرف لكنه مختلف بحرف أخير
        $almostCorrect = substr($this->validToken, 0, -1) . 'X';

        $this->artisan("project:seed-realistic --force --token={$almostCorrect} --count=2")
            ->expectsOutputToContain('المفتاح السري غير صحيح')
            ->assertExitCode(1);
    }

    // =========================================================================
    // 🧩 سيناريو 5 — خيار count
    // =========================================================================

    #[Test]
    public function يحترم_الأمر_خيار_count_لتحديد_عدد_العناصر(): void
    {
        $this->artisan('project:seed-realistic --count=1')
            ->expectsConfirmation(
                '⚠️  سيتم حذف جميع البيانات الحالية وإعادة تعبئتها. هل أنت متأكد؟',
                'yes'
            )
            ->assertExitCode(0);

        $this->assertEquals(1, Book::count());
    }
}
