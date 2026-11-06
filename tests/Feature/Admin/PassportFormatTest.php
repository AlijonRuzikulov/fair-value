<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A passport is two capital letters and seven digits; letters typed small
 * are made capital. The sign-in page of the classic look.
 */
class PassportFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_passport_is_two_capital_letters_and_seven_digits(): void
    {
        $this->actingAs(User::factory()->create());
        $person = ['type' => 'individual', 'last_name' => 'Karimov', 'first_name' => 'Anvar'];

        $this->post('/admin/clients', [...$person, 'passport_series' => 'ab', 'passport_number' => '123 45 67'])->assertSessionHasNoErrors();
        $client = Client::sole();
        $this->assertSame(['AB', '1234567'], [$client->passport_series, $client->passport_number]);

        $this->post('/admin/clients', [...$person, 'passport_series' => 'A1', 'passport_number' => '12345'])
            ->assertSessionHasErrors(['passport_series', 'passport_number']);

        $this->get('/admin/clients/create?type=individual')->assertOk()->assertSee('data-upper', false)->assertSee('pattern=', false);
    }

    public function test_the_sign_in_page_is_the_simple_one(): void
    {
        $this->withSession(['locale' => 'uz'])->get('/login')->assertOk()
            ->assertSee('simple-login', false)->assertSee('name="email"', false)->assertSee('name="password"', false)
            ->assertSee('Yordam olish')->assertSee(route('contact'), false);
    }
}
