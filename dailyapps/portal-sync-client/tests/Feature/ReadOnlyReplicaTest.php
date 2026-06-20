<?php

namespace Dailyapps\PortalSync\Tests\Feature;

use Dailyapps\PortalSync\Concerns\ReadOnlyReplica;
use Dailyapps\PortalSync\Exceptions\ReplicaIsReadOnlyException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReadOnlyReplicaTest extends TestCase
{
    private const string ID = 'w0000000-0000-7000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('replica_widgets');
        Schema::create('replica_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');           // sync-owned
            $table->string('note')->nullable(); // local to the child
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('replica_widgets');

        parent::tearDown();
    }

    private function seedWidget(): ReplicaWidget
    {
        return ReplicaWidget::withoutReplicaGuard(function () {
            $widget = new ReplicaWidget;
            $widget->forceFill(['id' => self::ID, 'name' => 'Acme', 'note' => null]);
            $widget->save();

            return $widget->fresh();
        });
    }

    public function test_editing_a_sync_owned_column_is_blocked(): void
    {
        $widget = $this->seedWidget();

        $this->expectException(ReplicaIsReadOnlyException::class);

        $widget->name = 'Renamed';
        $widget->save();
    }

    public function test_editing_only_local_columns_is_allowed(): void
    {
        $widget = $this->seedWidget();

        $widget->note = 'a local note';
        $widget->save();

        $this->assertDatabaseHas('replica_widgets', [
            'id' => self::ID,
            'name' => 'Acme',
            'note' => 'a local note',
        ]);
    }

    public function test_the_lifecycle_is_owned_by_sync(): void
    {
        $widget = $this->seedWidget();

        $this->expectException(ReplicaIsReadOnlyException::class);

        $widget->delete();
    }

    public function test_writes_to_sync_owned_columns_are_allowed_only_inside_without_replica_guard(): void
    {
        $this->seedWidget();

        $this->assertDatabaseHas('replica_widgets', ['id' => self::ID, 'name' => 'Acme']);
    }
}

class ReplicaWidget extends Model
{
    use ReadOnlyReplica;

    protected $table = 'replica_widgets';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function syncedColumns(): array
    {
        return ['id', 'name'];
    }
}
