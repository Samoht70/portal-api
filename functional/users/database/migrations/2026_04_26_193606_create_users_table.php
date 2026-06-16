<?php

use Functional\Organizations\Models\Site;
use Functional\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table
                ->portalUsers()
                ->portalForeignId(Site::class)
                ->portalForeignId(User::class, 'manager_id', nullable: true)
                ->softDeletes();

            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
