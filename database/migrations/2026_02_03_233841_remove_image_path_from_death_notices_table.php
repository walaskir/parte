<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * IMPORTANT: Before running this migration, ensure all images have been
     * migrated to MediaLibrary using: php artisan parte:migrate-images-to-media-library
     */
    public function up(): void
    {
        if (Schema::hasColumn('death_notices', 'image_path')) {
            Schema::table('death_notices', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('death_notices', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('source_url');
        });
    }
};
