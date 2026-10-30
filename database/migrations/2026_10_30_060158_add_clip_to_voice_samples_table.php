<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_samples', function (Blueprint $table) {
            // Đoạn của file được dùng để clone giọng (VieNeu chỉ dùng tối đa ~8 giây). null = dùng từ đầu file.
            $table->float('clip_start')->nullable()->after('duration');
            $table->float('clip_len')->nullable()->after('clip_start');
        });
    }

    public function down(): void
    {
        Schema::table('voice_samples', function (Blueprint $table) {
            $table->dropColumn(['clip_start', 'clip_len']);
        });
    }
};
