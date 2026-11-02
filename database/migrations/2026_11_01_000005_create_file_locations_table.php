<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_file_id')->constrained('inventory_files')->cascadeOnDelete();
            $table->foreignUuid('storage_source_id')->constrained('storage_sources')->cascadeOnDelete();
            $table->string('relative_path', 1000);
            $table->string('original_filename');
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('is_accessible')->default(true)->index();
            $table->timestamps();

            $table->index(['storage_source_id', 'is_accessible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_locations');
    }
};
