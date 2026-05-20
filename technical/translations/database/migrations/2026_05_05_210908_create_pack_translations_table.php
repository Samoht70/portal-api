<?php

use Functional\Applications\Models\Pack;
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
        Schema::create('pack_translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(Pack::class)->constrained();
            $table->string('locale')->index();
            $table->string('name');

            $table->unique(['pack_id', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pack_translations');
    }
};
