<?php

use Functional\Applications\Models\ApplicationRole;
use Functional\Users\Models\User;
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
        Schema::create('user_holds_application_roles', function (Blueprint $table) {
            $table->foreignIdFor(User::class)->constrained();
            $table->foreignIdFor(ApplicationRole::class)->constrained();
            $table->unsignedInteger('order')->nullable();
            $table->timestamps();
            $table->primary(['user_id', 'application_role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_holds_application_roles');
    }
};
