<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_files', function (Blueprint $table) {
            $table->boolean('is_directory')->default(false)->after('category_id')->index();
        });

        Schema::table('scan_histories', function (Blueprint $table) {
            $table->string('scan_mode', 32)->default('file')->after('is_recursive');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_files', function (Blueprint $table) {
            $table->dropIndex(['is_directory']);
            $table->dropColumn('is_directory');
        });

        Schema::table('scan_histories', function (Blueprint $table) {
            $table->dropColumn('scan_mode');
        });
    }
};
