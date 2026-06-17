<?php

use Functional\Applications\Models\Application;
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
        Schema::create('application_sync_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Application::class)->unique()->constrained();
            $table->string('endpoint_url');
            $table->string('secret');
            $table->boolean('sync_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_sync_endpoints');
    }
};
