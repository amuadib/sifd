<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('storage_source_id')->nullable()->constrained('storage_sources')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scanned_path');
            $table->boolean('is_recursive')->default(true);
            $table->unsignedInteger('files_found')->default(0);
            $table->unsignedInteger('files_added')->default(0);
            $table->unsignedInteger('locations_added')->default(0);
            $table->unsignedInteger('files_unmodified')->default(0);
            $table->unsignedInteger('files_failed')->default(0);
            $table->decimal('duration_seconds', 8, 2)->default(0.00);
            $table->string('status', 32)->default('completed');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_histories');
    }
};
