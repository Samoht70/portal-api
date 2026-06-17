<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->bigIncrements('sequence');
            $table->uuid('id')->unique();
            $table->string('aggregate_type');
            $table->uuid('aggregate_id')->index();
            $table->string('event_type');
            $table->json('payload');
            $table->uuid('tenant_scope')->nullable()->index();
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};
