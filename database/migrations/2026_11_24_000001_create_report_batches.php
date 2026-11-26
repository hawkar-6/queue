<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignUuid('batch_id')->nullable()->constrained('report_batches')->cascadeOnDelete();
            $table->string('class_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn(['batch_id', 'class_name']);
        });
        Schema::dropIfExists('report_batches');
    }
};
