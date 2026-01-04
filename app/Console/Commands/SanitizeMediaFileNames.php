<?php

namespace App\Console\Commands;

use App\Models\DeathNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SanitizeMediaFileNames extends Command
{
    protected $signature = 'media:sanitize-filenames {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Sanitize media file names by replacing spaces and special characters with underscores';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $media = DB::table('media')
            ->where('model_type', 'App\\Models\\DeathNotice')
            ->get();

        $changedCount = 0;

        foreach ($media as $mediaItem) {
            $oldFileName = $mediaItem->file_name;

            // Decode URL-encoded characters first
            $decoded = urldecode($oldFileName);

            // Replace spaces and special characters with underscores
            $newFileName = $this->sanitizeFileName($decoded);

            if ($oldFileName !== $newFileName) {
                $this->info("File: {$oldFileName}");
                $this->info("  → {$newFileName}");

                if (! $dryRun) {
                    // Get the full old and new paths using the hash from model
                    $model = DeathNotice::find($mediaItem->model_id);

                    if (! $model) {
                        $this->error("  ✗ Model not found for media ID {$mediaItem->id}");

                        continue;
                    }

                    $basePath = $model->hash;
                    $oldPath = $basePath.'/'.$oldFileName;
                    $newPath = $basePath.'/'.$newFileName;

                    // Rename file in storage
                    $disk = Storage::disk($mediaItem->disk);

                    if ($disk->exists($oldPath)) {
                        $disk->move($oldPath, $newPath);
                        $this->info('  ✓ Renamed file in storage');
                    } else {
                        $this->warn("  ! File not found in storage: {$oldPath}");
                    }

                    // Update database
                    DB::table('media')
                        ->where('id', $mediaItem->id)
                        ->update(['file_name' => $newFileName]);

                    $this->info('  ✓ Updated database');
                }

                $changedCount++;
            }
        }

        if ($dryRun) {
            $this->info("\nDRY RUN: {$changedCount} files would be renamed.");
            $this->info('Run without --dry-run to apply changes.');
        } else {
            $this->info("\n✓ Successfully renamed {$changedCount} files.");
        }

        return Command::SUCCESS;
    }

    private function sanitizeFileName(string $fileName): string
    {
        // Get file extension
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);

        // Replace spaces and special characters with underscores
        $sanitized = preg_replace('/[^\w\-.]/', '_', $nameWithoutExt);

        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', $sanitized);

        // Trim underscores from start and end
        $sanitized = trim($sanitized, '_');

        return $sanitized.'.'.$extension;
    }
}
