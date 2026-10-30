<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_sample_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file');               // đường dẫn wav (disk local)
            $table->float('start');                // giây trong file nguồn
            $table->float('duration');
            $table->text('text')->nullable();      // lời thoại (đã sửa tay)
            $table->boolean('included')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_clips');
    }
};
