<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QueueFlowTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $attrs = []): Service
    {
        return Service::factory()->create($attrs + ['code' => 'A', 'avg_minutes' => 10]);
    }

    private function take(Service $s): array
    {
        return $this->postJson('/api/v1/tickets', ['service_id' => $s->id])->assertCreated()->json();
    }

    public function test_tickets_are_numbered_sequentially_per_service(): void
    {
        $a = $this->service();
        $b = Service::factory()->create(['code' => 'B']);

        $this->assertSame('A001', $this->take($a)['data']['number']);
        $this->assertSame('A002', $this->take($a)['data']['number']);
        $this->assertSame('B001', $this->take($b)['data']['number']);
    }

    public function test_numbering_restarts_each_day(): void
    {
        $a = $this->service();
        $this->travelTo(now()->subDay());
        $this->take($a);
        $this->take($a);
        $this->travelBack();

        $this->assertSame('A001', $this->take($a)['data']['number']);
    }

    public function test_closed_service_rejects_new_tickets(): void
    {
        $a = $this->service(['is_open' => false]);

        $this->postJson('/api/v1/tickets', ['service_id' => $a->id])
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_patient_sees_position_and_estimated_wait(): void
    {
        $a = $this->service(['avg_minutes' => 8]);
        $this->take($a);
        $this->take($a);
        $third = $this->take($a);

        $this->getJson('/api/v1/tickets/'.$third['data']['id'])
            ->assertOk()
            ->assertJsonPath('ahead', 2)
            ->assertJsonPath('estimated_wait_minutes', 16);
    }

    public function test_staff_calls_patients_in_order(): void
    {
        $a = $this->service();
        $this->take($a);
        $this->take($a);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/services/{$a->id}/call-next", ['counter' => '1'])
            ->assertOk()->assertJsonPath('data.number', 'A001')->assertJsonPath('data.status', 'called');
        $this->postJson("/api/v1/services/{$a->id}/call-next", ['counter' => '2'])
            ->assertOk()->assertJsonPath('data.number', 'A002');
        $this->postJson("/api/v1/services/{$a->id}/call-next", ['counter' => '1'])
            ->assertNotFound();
    }

    public function test_calling_requires_authentication(): void
    {
        $a = $this->service();
        $this->postJson("/api/v1/services/{$a->id}/call-next", ['counter' => '1'])->assertUnauthorized();
    }

    public function test_ticket_lifecycle_and_invalid_transitions(): void
    {
        $a = $this->service();
        $id = $this->take($a)['data']['id'];
        Sanctum::actingAs(User::factory()->create());

        // cannot finish a ticket that was never called
        $this->patchJson("/api/v1/tickets/{$id}/status", ['status' => 'done'])->assertUnprocessable();

        $this->postJson("/api/v1/services/{$a->id}/call-next", ['counter' => '3'])->assertOk();
        $this->patchJson("/api/v1/tickets/{$id}/status", ['status' => 'serving'])->assertOk()->assertJsonPath('data.status', 'serving');
        $this->patchJson("/api/v1/tickets/{$id}/status", ['status' => 'done'])->assertOk()->assertJsonPath('data.status', 'done');

        $ticket = Ticket::where('public_id', $id)->first();
        $this->assertNotNull($ticket->served_at);
        $this->assertNotNull($ticket->finished_at);

        // done is terminal
        $this->patchJson("/api/v1/tickets/{$id}/status", ['status' => 'waiting'])->assertUnprocessable();
    }

    public function test_board_shows_now_serving_and_next(): void
    {
        $a = $this->service();
        foreach (range(1, 4) as $_) {
            $this->take($a);
        }
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/services/{$a->id}/call-next", ['counter' => '5']);

        $this->getJson("/api/v1/services/{$a->id}/board")
            ->assertOk()
            ->assertJsonPath('now.0.number', 'A001')
            ->assertJsonPath('now.0.counter', '5')
            ->assertJsonPath('next', ['A002', 'A003', 'A004'])
            ->assertJsonPath('waiting', 3);
    }

    public function test_only_admins_manage_services_and_see_stats(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => Role::Staff]));
        $this->postJson('/api/v1/services', ['code' => 'D', 'name' => 'Lab'])->assertForbidden();
        $this->getJson('/api/v1/stats/today')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => Role::Admin]));
        $this->postJson('/api/v1/services', ['code' => 'd', 'name' => 'Lab'])->assertCreated()->assertJsonPath('data.code', 'D');
        $this->getJson('/api/v1/stats/today')->assertOk()->assertJsonPath('services.0.service', 'D');
    }

    public function test_login_issues_a_token_and_rejects_bad_passwords(): void
    {
        User::factory()->create(['email' => 'staff@example.com', 'password' => 'secret-pass']);

        $this->postJson('/api/v1/auth/login', ['email' => 'staff@example.com', 'password' => 'nope'])
            ->assertUnprocessable();
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'staff@example.com', 'password' => 'secret-pass'])
            ->assertOk()->json('token');

        $this->assertNotEmpty($token);
    }
}
