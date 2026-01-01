<?php

namespace App\Console\Commands;

use App\Services\DeathNoticeService;
use Illuminate\Console\Command;
use Symfony\Component\HttpFoundation\Response;

class DownloadDeathNotices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parte:download
                            {--source=* : Specific funeral service sources to download from (e.g., sadovy-jan, pshajdukova, psbk)}
                            {--all : Download from all sources (default)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stáhnout oznámení úmrtí z pohřebních služeb';

    /**
     * Execute the console command.
     */
    public function handle(DeathNoticeService $service): int
    {
        $this->info('Zahajuji stahování oznámení úmrtí...');

        // Determine which sources to use
        $sources = $this->option('source');

        if (empty($sources) || $this->option('all')) {
            $sources = $service->getAvailableSources();
            $this->info('Stahuji ze všech zdrojů: ' . implode(', ', $sources));
        } else {
            // Validate sources
            $availableSources = $service->getAvailableSources();
            $invalidSources = array_diff($sources, $availableSources);

            if (!empty($invalidSources)) {
                $this->error('Neplatné zdroje: ' . implode(', ', $invalidSources));
                $this->info('Dostupné zdroje: ' . implode(', ', $availableSources));
                return Response::HTTP_UNPROCESSABLE_ENTITY;
            }

            $this->info('Stahuji ze zdrojů: ' . implode(', ', $sources));
        }

        // Download notices
        $results = $service->downloadNotices($sources);

        // Display results
        $this->newLine();
        $this->info('=== Výsledky stahování ===');
        $this->table(
            ['Metrika', 'Hodnota'],
            [
                ['Celkem nalezeno', $results['total']],
                ['Nově přidáno', $results['new']],
                ['Duplicity', $results['duplicates']],
                ['Chyby', $results['errors']],
            ]
        );

        if ($results['new'] > 0) {
            $this->info("✓ Úspěšně staženo {$results['new']} nových oznámení");
        } else {
            $this->warn('Nebyla nalezena žádná nová oznámení');
        }

        if ($results['errors'] > 0) {
            $this->error("⚠ Vyskytly se {$results['errors']} chyby");
            return Response::HTTP_PARTIAL_CONTENT;
        }

        return Response::HTTP_OK;
    }
}
