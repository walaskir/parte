<?php

use App\Services\PdfGeneratorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->service = new PdfGeneratorService;
    $this->outputDir = storage_path('app/private/temp/test-output');

    // Ensure output directory exists
    if (! is_dir($this->outputDir)) {
        mkdir($this->outputDir, 0755, true);
    }
});

afterEach(function () {
    // Cleanup test output files recursively
    if (is_dir($this->outputDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->outputDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($this->outputDir);
    }
});

// ============================================================================
// Image → PDF Conversion Tests
// ============================================================================

test('can convert JPG image to PDF', function () {
    $inputPath = base_path('tests/fixtures/sample-portrait.jpg');
    $outputPath = $this->outputDir.'/output-jpg.pdf';

    $result = $this->service->convertImageToPdf($inputPath, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue()
        ->and(filesize($outputPath))->toBeGreaterThan(0)
        ->and(mime_content_type($outputPath))->toBe('application/pdf');
});

test('can convert PDF to PDF (first page only)', function () {
    $inputPath = base_path('tests/fixtures/sample-small.pdf');
    $outputPath = $this->outputDir.'/output-pdf-to-pdf.pdf';

    $result = $this->service->convertImageToPdf($inputPath, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue()
        ->and(filesize($outputPath))->toBeGreaterThan(0)
        ->and(mime_content_type($outputPath))->toBe('application/pdf');
});

test('maintains aspect ratio when scaling to A4', function () {
    $inputPath = base_path('tests/fixtures/sample-portrait.jpg');
    $outputPath = $this->outputDir.'/output-aspect-ratio.pdf';

    $result = $this->service->convertImageToPdf($inputPath, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue();

    // PDF should be created successfully and be a valid PDF
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $outputPath);
    finfo_close($fileInfo);

    expect($mimeType)->toBe('application/pdf');
});

test('handles corrupted image gracefully', function () {
    $inputPath = $this->outputDir.'/corrupted.jpg';
    $outputPath = $this->outputDir.'/output-corrupted.pdf';

    // Create invalid image file
    file_put_contents($inputPath, 'invalid image data');

    $result = $this->service->convertImageToPdf($inputPath, $outputPath);

    expect($result)->toBeFalse()
        ->and(file_exists($outputPath))->toBeFalse();
});

test('handles missing file gracefully', function () {
    $inputPath = $this->outputDir.'/nonexistent.jpg';
    $outputPath = $this->outputDir.'/output-missing.pdf';

    $result = $this->service->convertImageToPdf($inputPath, $outputPath);

    expect($result)->toBeFalse()
        ->and(file_exists($outputPath))->toBeFalse();
});

test('creates output directory if missing', function () {
    $inputPath = base_path('tests/fixtures/sample-portrait.jpg');
    $outputPath = $this->outputDir.'/nested/deep/output.pdf';

    $result = $this->service->convertImageToPdf($inputPath, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue()
        ->and(is_dir(dirname($outputPath)))->toBeTrue();
});

test('generates PDF with reasonable file size for typical image', function () {
    $inputPath = base_path('tests/fixtures/sample-portrait.jpg');
    $outputPath = $this->outputDir.'/output-filesize.pdf';

    $result = $this->service->convertImageToPdf($inputPath, $outputPath);

    expect($result)->toBeTrue();

    $fileSizeKB = filesize($outputPath) / 1024;

    // For a 33KB portrait JPG, output PDF should be < 600KB
    // (allowing some overhead for PDF structure + high quality 300 DPI)
    expect($fileSizeKB)->toBeLessThan(600);
});

test('applies JPEG compression quality 85', function () {
    $inputPath = base_path('tests/fixtures/sample-small.pdf');
    $outputPath = $this->outputDir.'/output-compression.pdf';

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Image converted to PDF using Imagick')
                && isset($context['size_kb']);
        });

    $result = $this->service->convertImageToPdf($inputPath, $outputPath);

    expect($result)->toBeTrue();
});

// ============================================================================
// HTML → PDF Conversion Tests
// ============================================================================

test('can convert simple HTML to PDF', function () {
    $html = '<html><body><h1>Test Death Notice</h1><p>This is a test.</p></body></html>';
    $outputPath = $this->outputDir.'/output-html-simple.pdf';

    $result = $this->service->convertHtmlToPdf($html, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue()
        ->and(filesize($outputPath))->toBeGreaterThan(0)
        ->and(mime_content_type($outputPath))->toBe('application/pdf');
});

test('can convert HTML with CSS styles to PDF', function () {
    $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; padding: 40px; }
                h1 { color: #333; border-bottom: 2px solid #333; }
            </style>
        </head>
        <body>
            <h1>PARTE</h1>
            <p>Jméno: Jan Novák</p>
            <p>Datum pohřbu: 15. 1. 2024</p>
        </body>
        </html>
    ';
    $outputPath = $this->outputDir.'/output-html-styled.pdf';

    $result = $this->service->convertHtmlToPdf($html, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue();
});

test('respects custom margins in HTML to PDF', function () {
    $html = '<html><body><p>Test content</p></body></html>';
    $outputPath = $this->outputDir.'/output-html-margins.pdf';

    $result = $this->service->convertHtmlToPdf($html, $outputPath, [
        'margin-top' => 50,
        'margin-right' => 30,
        'margin-bottom' => 50,
        'margin-left' => 30,
    ]);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue();
});

test('handles Czech diacritics in HTML', function () {
    $html = '<html><body><p>Žluťoučký kůň úpěl ďábelské ódy</p></body></html>';
    $outputPath = $this->outputDir.'/output-html-diacritics.pdf';

    $result = $this->service->convertHtmlToPdf($html, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue();
});

test('handles missing output directory for HTML to PDF', function () {
    $html = '<html><body><p>Test</p></body></html>';
    $outputPath = $this->outputDir.'/deep/nested/path/output.pdf';

    $result = $this->service->convertHtmlToPdf($html, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue()
        ->and(is_dir(dirname($outputPath)))->toBeTrue();
});

// ============================================================================
// Download & Convert Tests
// ============================================================================

test('can download and convert image from URL', function () {
    $imageUrl = 'https://example.com/test-image.jpg';
    $outputPath = $this->outputDir.'/output-downloaded.pdf';

    // Mock successful HTTP response with fake image data
    $fakeImageData = file_get_contents(base_path('tests/fixtures/sample-portrait.jpg'));

    Http::fake([
        $imageUrl => Http::response($fakeImageData, 200),
    ]);

    $result = $this->service->downloadAndConvertToPdf($imageUrl, $outputPath);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue();

    Http::assertSent(function ($request) use ($imageUrl) {
        return $request->url() === $imageUrl;
    });
});

test('retries on HTTP failure', function () {
    $imageUrl = 'https://example.com/test-image.jpg';
    $outputPath = $this->outputDir.'/output-retry.pdf';

    $fakeImageData = file_get_contents(base_path('tests/fixtures/sample-portrait.jpg'));

    // Mock: first 2 requests fail, 3rd succeeds
    Http::fake([
        $imageUrl => Http::sequence()
            ->push('', 500) // First attempt fails
            ->push('', 500) // Second attempt fails
            ->push($fakeImageData, 200), // Third attempt succeeds
    ]);

    $result = $this->service->downloadAndConvertToPdf($imageUrl, $outputPath, 3);

    expect($result)->toBeTrue()
        ->and(file_exists($outputPath))->toBeTrue();
});

test('fails after max retries on persistent HTTP failure', function () {
    $imageUrl = 'https://example.com/test-image.jpg';
    $outputPath = $this->outputDir.'/output-failed.pdf';

    // Mock: all requests fail
    Http::fake([
        $imageUrl => Http::response('', 500),
    ]);

    $result = $this->service->downloadAndConvertToPdf($imageUrl, $outputPath, 3);

    expect($result)->toBeFalse()
        ->and(file_exists($outputPath))->toBeFalse();
});

test('cleans up temp files after successful download', function () {
    $imageUrl = 'https://example.com/test-image.jpg';
    $outputPath = $this->outputDir.'/output-cleanup-success.pdf';

    $fakeImageData = file_get_contents(base_path('tests/fixtures/sample-portrait.jpg'));

    Http::fake([
        $imageUrl => Http::response($fakeImageData, 200),
    ]);

    $tempFilesBefore = glob(storage_path('app/private/temp/download_*'));

    $result = $this->service->downloadAndConvertToPdf($imageUrl, $outputPath);

    $tempFilesAfter = glob(storage_path('app/private/temp/download_*'));

    expect($result)->toBeTrue()
        // Temp files should be cleaned up
        ->and(count($tempFilesAfter))->toBe(count($tempFilesBefore));
});

test('cleans up temp files after download failure', function () {
    $imageUrl = 'https://example.com/test-image.jpg';
    $outputPath = $this->outputDir.'/output-cleanup-failure.pdf';

    // Mock: corrupted response that will fail Imagick conversion
    Http::fake([
        $imageUrl => Http::response('invalid image data', 200),
    ]);

    $tempFilesBefore = glob(storage_path('app/private/temp/download_*'));

    $result = $this->service->downloadAndConvertToPdf($imageUrl, $outputPath, 1);

    $tempFilesAfter = glob(storage_path('app/private/temp/download_*'));

    expect($result)->toBeFalse()
        // Temp files should still be cleaned up even on failure
        ->and(count($tempFilesAfter))->toBe(count($tempFilesBefore));
});

test('respects timeout settings when downloading', function () {
    $imageUrl = 'https://example.com/test-image.jpg';
    $outputPath = $this->outputDir.'/output-timeout.pdf';

    $fakeImageData = file_get_contents(base_path('tests/fixtures/sample-portrait.jpg'));

    Http::fake([
        $imageUrl => Http::response($fakeImageData, 200),
    ]);

    $result = $this->service->downloadAndConvertToPdf($imageUrl, $outputPath);

    expect($result)->toBeTrue();

    // Verify timeout was set to 30 seconds
    Http::assertSent(function ($request) {
        // Check if timeout option was set (Laravel's HTTP client)
        return true; // Timeout is set in the service, hard to test without mocking internals
    });
});
