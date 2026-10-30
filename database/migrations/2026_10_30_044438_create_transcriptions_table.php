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
        Schema::create('transcriptions', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('video_id')->nullable();
            $table->string('title');
            $table->string('channel')->nullable();
            $table->string('thumbnail')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('method')->default('auto'); // auto|whisper
            $table->string('source')->nullable();      // subtitle|whisper
            $table->string('status')->default('pending'); // pending|processing|done|failed
            $table->longText('text')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcriptions');
    }
};
