<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('death_notices', function (Blueprint $table) {
            $table->text('announcement_text')->nullable()->after('source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('death_notices', function (Blueprint $table) {
            $table->dropColumn('announcement_text');
        });
    }
};
