<?php

use Functional\Applications\Models\ApplicationRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('application_role_translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(ApplicationRole::class)->constrained();
            $table->string('locale')->index();
            $table->string('name');

            $table->unique(['application_role_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_role_translations');
    }
};
