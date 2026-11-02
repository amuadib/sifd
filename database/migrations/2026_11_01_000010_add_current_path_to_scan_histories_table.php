<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_histories', function (Blueprint $table) {
            $table->string('current_path')->nullable()->after('scanned_path');
        });
    }

    public function down(): void
    {
        Schema::table('scan_histories', function (Blueprint $table) {
            $table->dropColumn('current_path');
        });
    }
};
