<?php

namespace Tests\Feature\Site;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_leave_a_request(): void
    {
        $this->from('/contact')->post('/request', [
            'name' => 'Alisher',
            'phone' => '+998 90 123-45-67',
            'service' => 'real-estate',
            'message' => 'Kvartirani baholatmoqchiman',
        ])->assertRedirect('/contact#request')->assertSessionHas('lead_sent');

        $lead = Lead::sole();
        $this->assertSame('Alisher', $lead->name);
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertSame('real-estate', $lead->service);
    }

    public function test_request_is_validated_in_its_own_error_bag(): void
    {
        $this->from('/')->post('/request', ['name' => '', 'phone' => 'abc', 'service' => 'nope'])
            ->assertRedirect('/')
            ->assertSessionHasErrorsIn('lead', ['name', 'phone', 'service']);

        $this->assertSame(0, Lead::count());
    }

    public function test_bots_filling_the_hidden_field_are_ignored(): void
    {
        $this->from('/')->post('/request', ['name' => 'Bot', 'phone' => '+998901234567', 'website' => 'http://spam'])
            ->assertRedirect('/');

        $this->assertSame(0, Lead::count());
    }

    public function test_telegram_notification_is_sent_when_configured(): void
    {
        config(['services.telegram.bot_token' => 'TOKEN', 'services.telegram.chat_id' => '123']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->post('/request', ['name' => 'Malika', 'phone' => '+998 91 111-22-33']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'botTOKEN/sendMessage')
            && str_contains($request['text'], 'Malika'));
    }
}
