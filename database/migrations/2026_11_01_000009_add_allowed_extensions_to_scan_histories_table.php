<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_histories', function (Blueprint $table) {
            $table->string('allowed_extensions')->nullable()->after('scan_mode');
        });
    }

    public function down(): void
    {
        Schema::table('scan_histories', function (Blueprint $table) {
            $table->dropColumn('allowed_extensions');
        });
    }
};
