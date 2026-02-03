<?php

namespace App\Console\Commands;

use App\Models\DeathNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandAlias;

class MigrateImagesToMediaLibraryCommand extends Command
{
    protected $signature = 'parte:migrate-images-to-media-library
                            {--dry-run : Show what would be migrated without making changes}
                            {--cleanup : Remove old image files after successful migration}';

    protected $description = 'Migrate existing parte images from direct storage to MediaLibrary';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $shouldCleanup = $this->option('cleanup');

        if ($isDryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        // Find all records with image_path set
        $notices = DeathNotice::whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->get();

        if ($notices->isEmpty()) {
            $this->info('No records with image_path found. Nothing to migrate.');

            return CommandAlias::SUCCESS;
        }

        $this->info("Found {$notices->count()} records with image_path to migrate.");
        $this->newLine();

        $bar = $this->output->createProgressBar($notices->count());
        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($notices as $notice) {
            try {
                $oldPath = Storage::disk('local')->path($notice->image_path);

                if (! file_exists($oldPath)) {
                    $this->warn("File not found for {$notice->hash}: {$notice->image_path}");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Check if already has original_image in MediaLibrary
                if ($notice->getFirstMedia('original_image')) {
                    $this->line("Skipping {$notice->hash} - already has original_image in MediaLibrary");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                if ($isDryRun) {
                    $this->line("Would migrate: {$notice->hash} from {$notice->image_path}");
                    $migrated++;
                    $bar->advance();

                    continue;
                }

                // Get extension from existing file
                $extension = pathinfo($oldPath, PATHINFO_EXTENSION) ?: 'png';

                // Add to MediaLibrary preserving original during migration
                $notice->addMedia($oldPath)
                    ->preservingOriginal()
                    ->usingFileName("{$notice->hash}.{$extension}")
                    ->toMediaCollection('original_image');

                // Clear image_path (even though column will be removed)
                $notice->image_path = null;
                $notice->save();

                $migrated++;

                // Cleanup old file if requested
                if ($shouldCleanup && file_exists($oldPath)) {
                    unlink($oldPath);
                }
            } catch (\Exception $e) {
                $this->error("Error migrating {$notice->hash}: {$e->getMessage()}");
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Migration completed!');
        $this->info("- Migrated: {$migrated}");
        $this->info("- Skipped: {$skipped}");
        $this->info("- Errors: {$errors}");

        if (! $isDryRun && ! $shouldCleanup && $migrated > 0) {
            $this->newLine();
            $this->warn('Old image files were preserved. Run with --cleanup to remove them.');
            $this->warn('Or manually remove: storage/app/private/parte-images/');
        }

        return CommandAlias::SUCCESS;
    }
}
