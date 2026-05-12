<?php

use Functional\Applications\Models\Application;
use Functional\Applications\Models\Pack;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Application::class)->constrained();
            $table->string('locale')->index();
            $table->string('name');
            $table->text('description')->nullable();

            $table->unique(['application_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_translations');
    }
};
