<?php

namespace Dailyapps\PortalShared\Tests\Feature;

use Dailyapps\PortalShared\Concerns\ReadOnlyReplica;
use Dailyapps\PortalShared\Exceptions\ReplicaIsReadOnlyException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReadOnlyReplicaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('replica_widgets');
        Schema::create('replica_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('replica_widgets');

        parent::tearDown();
    }

    public function test_a_replica_model_blocks_writes_through_the_model_layer(): void
    {
        $this->expectException(ReplicaIsReadOnlyException::class);

        $widget = new ReplicaWidget;
        $widget->forceFill(['id' => 'w0000000-0000-7000-8000-000000000001', 'name' => 'A']);
        $widget->save();
    }

    public function test_writes_are_allowed_only_inside_without_replica_guard(): void
    {
        ReplicaWidget::withoutReplicaGuard(function () {
            $widget = new ReplicaWidget;
            $widget->forceFill(['id' => 'w0000000-0000-7000-8000-000000000002', 'name' => 'A']);
            $widget->save();
        });

        $this->assertDatabaseHas('replica_widgets', ['id' => 'w0000000-0000-7000-8000-000000000002']);
    }
}

class ReplicaWidget extends Model
{
    use ReadOnlyReplica;

    protected $table = 'replica_widgets';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;
}
