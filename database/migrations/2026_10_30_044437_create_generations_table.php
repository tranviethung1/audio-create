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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('text');
            $table->foreignId('voice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('preset_voice')->nullable();
            $table->string('voice_label');
            $table->string('status')->default('pending'); // pending|processing|done|failed
            $table->string('path')->nullable();
            $table->float('duration')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
