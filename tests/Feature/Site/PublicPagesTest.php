<?php

namespace Tests\Feature\Site;

use App\Support\Services;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return [['oz', 'Баҳолаш'], ['uz', 'Baholash'], ['ru', 'Оценка']];
    }

    #[DataProvider('locales')]
    public function test_pages_render_in_every_language(string $locale, string $word): void
    {
        $this->withSession(['locale' => $locale]);

        $this->get('/')->assertOk()->assertSee($word)->assertSee('lang="'.config("fairvalue.locales.$locale.html").'"', false);
        $this->get('/services')->assertOk();
        foreach (array_keys(Services::all()) as $slug) {
            $this->get('/services/'.$slug)->assertOk();
        }
        $this->get('/about')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/verify')->assertOk();
    }

    public function test_language_can_be_switched(): void
    {
        $this->get('/lang/ru')->assertRedirect();
        $this->get('/')->assertSee('Оставить заявку');

        $this->get('/?lang=uz')->assertSee('Ariza qoldirish');
    }

    public function test_unknown_language_and_service_return_404(): void
    {
        $this->get('/lang/xx')->assertNotFound();
        $this->get('/services/unknown')->assertNotFound();
    }

    public function test_sitemap_lists_services(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('services.show', 'damage'), false);
    }
}
