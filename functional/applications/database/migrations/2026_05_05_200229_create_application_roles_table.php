<?php

use Functional\Applications\Models\Application;
use Functional\Applications\Models\RoleDefinition;
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
        Schema::create('application_roles', function (Blueprint $table) {
            $table->foreignIdFor(Application::class)->constrained();
            $table->foreignIdFor(RoleDefinition::class)->constrained();
            $table->timestamps();

            $table->primary(['application_id', 'role_definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_roles');
    }
};
