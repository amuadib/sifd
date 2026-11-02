<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name')->index();
            $table->string('extension', 32)->index();
            $table->string('mime_type')->nullable()->index();
            $table->string('file_type_group', 32)->default('other')->index();
            $table->unsignedBigInteger('file_size')->default(0)->index();
            $table->string('checksum_sha256', 64)->unique();
            $table->json('metadata')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('preview_status', 32)->default('none')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_files');
    }
};
