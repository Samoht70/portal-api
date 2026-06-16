<?php

use Functional\Applications\Models\Application;
use Functional\Organizations\Models\Client;
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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Client::class)->constrained();
            $table->foreignIdFor(Application::class)->constrained();
            $table->unsignedInteger('licenses')->default(0);
            $table->timestamps();
            $table->unique(['client_id', 'application_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
