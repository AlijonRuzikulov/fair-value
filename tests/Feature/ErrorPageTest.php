<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The error pages of the framework speak the language of the user, not English. */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_and_a_forbidden_one_are_in_uzbek(): void
    {
        $this->actingAs(User::factory()->operator()->create())->withSession(['locale' => 'uz']);

        // An address that matches no route is answered before the session is read: in the default language (Cyrillic).
        $this->get('/admin/no-such-page')->assertNotFound()->assertSee('Саҳифа топилмади')->assertDontSee('Not Found');
        $this->get('/admin/settings')->assertForbidden()->assertSee('Ruxsat yo‘q')
            ->assertSee('Bu amal uchun sizda ruxsat yo‘q.')->assertDontSee('This action is unauthorized.');
    }

    public function test_they_are_in_russian_too(): void
    {
        $this->actingAs(User::factory()->operator()->create())->withSession(['locale' => 'ru']);

        $this->get('/admin/settings')->assertForbidden()->assertSee('У вас нет прав на это действие.');
    }
}
