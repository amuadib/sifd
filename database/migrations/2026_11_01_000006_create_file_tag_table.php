<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_tag', function (Blueprint $table) {
            $table->foreignUuid('inventory_file_id')->constrained('inventory_files')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['inventory_file_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_tag');
    }
};
