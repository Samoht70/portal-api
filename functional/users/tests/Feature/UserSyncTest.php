<?php

namespace Functional\Users\Tests\Feature;

use Dailyapps\EventDistribution\Models\DomainEventRecord;
use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class UserSyncTest extends TestCase
{
    /**
     * @return Collection<int, DomainEventRecord>
     */
    private function eventsFor(User $user): Collection
    {
        return DomainEventRecord::query()
            ->where('aggregate_id', $user->getKey())
            ->orderBy('sequence')
            ->get();
    }

    private function userInClient(Client $client): User
    {
        return User::factory()
            ->withoutManager()
            ->for(Site::factory()->for($client))
            ->create();
    }

    public function test_creating_a_user_records_a_tenant_scoped_upsert(): void
    {
        $client = Client::factory()->create();
        $user = $this->userInClient($client);

        $events = $this->eventsFor($user);

        $this->assertCount(1, $events);
        $this->assertSame('users.upserted', $events[0]->event_type);
        $this->assertSame($client->getKey(), $events[0]->tenant_scope);
        $this->assertSame($user->email, $events[0]->payload['email']);
        $this->assertNull($events[0]->published_at);
    }

    public function test_the_payload_never_carries_secrets(): void
    {
        $user = $this->userInClient(Client::factory()->create());

        $payload = $this->eventsFor($user)->last()->payload;

        $this->assertArrayHasKey('email', $payload);
        $this->assertArrayNotHasKey('password', $payload);
    }

    public function test_updating_a_user_appends_an_upsert_with_a_higher_sequence(): void
    {
        $user = $this->userInClient(Client::factory()->create());

        $user->update(['lastname' => 'Renamed']);

        $events = $this->eventsFor($user);

        $this->assertCount(2, $events);
        $this->assertSame('users.upserted', $events[1]->event_type);
        $this->assertSame('Renamed', $events[1]->payload['lastname']);
        $this->assertGreaterThan($events[0]->sequence, $events[1]->sequence);
    }

    public function test_soft_delete_then_restore_surface_as_delete_then_upsert(): void
    {
        $user = $this->userInClient(Client::factory()->create());

        $user->delete();
        $user->restore();

        $events = $this->eventsFor($user);

        $this->assertSame(
            ['users.upserted', 'users.deleted', 'users.upserted'],
            $events->pluck('event_type')->all()
        );
        $this->assertNotNull($events[1]->payload['deleted_at']);
        $this->assertNull($events[2]->payload['deleted_at']);
    }

    public function test_tenant_scope_resolves_even_when_the_owning_site_is_soft_deleted(): void
    {
        $client = Client::factory()->create();
        $user = $this->userInClient($client);

        $user->site->delete();
        $user->update(['lastname' => 'Moved']);

        $event = $this->eventsFor($user)->last();

        $this->assertSame('users.upserted', $event->event_type);
        $this->assertSame($client->getKey(), $event->tenant_scope);
    }

    public function test_a_rolled_back_transaction_records_no_event(): void
    {
        $user = $this->userInClient(Client::factory()->create());
        $before = $this->eventsFor($user)->count();

        try {
            DB::transaction(function () use ($user) {
                $user->update(['lastname' => 'Doomed']);

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected — the savepoint rolls back the update and its outbox row together
        }

        $this->assertCount($before, $this->eventsFor($user));
        $this->assertNotSame('Doomed', $user->fresh()->lastname);
    }

    public function test_snapshot_query_is_scoped_to_the_subscribed_clients_sites(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();

        $mine = $this->userInClient($client);
        $theirs = $this->userInClient($other);

        $ids = User::syncSnapshotQuery([$client->getKey()])->pluck('id')->all();

        $this->assertContains($mine->getKey(), $ids);
        $this->assertNotContains($theirs->getKey(), $ids);
    }
}
