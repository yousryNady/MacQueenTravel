<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locks', function (Blueprint $table) {
            $table->id();
            $table->string('lockable_type');
            $table->unsignedBigInteger('lockable_id');
            $table->string('lock_key')->unique();
            $table->string('owner');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['lockable_type', 'lockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locks');
    }
};
