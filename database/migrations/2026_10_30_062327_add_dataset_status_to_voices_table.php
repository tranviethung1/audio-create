<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voices', function (Blueprint $table) {
            $table->string('dataset_status')->nullable(); // null|processing|ready|failed
            $table->text('dataset_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('voices', function (Blueprint $table) {
            $table->dropColumn(['dataset_status', 'dataset_error']);
        });
    }
};
