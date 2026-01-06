<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Imagick;

class PdfGeneratorService
{
    /**
     * Convert image to PDF using Imagick with compression.
     *
     * Strategy:
     * - 300 DPI for maximum quality (per requirement)
     * - JPEG compression quality 85 (minimal visual loss)
     * - Fit to A4 format (210mm x 297mm)
     * - Center image on white canvas
     * - Target file size: <1MB for typical images
     *
     * @param  string  $imagePath  Path to source image (JPG, PNG, or PDF)
     * @param  string  $outputPath  Path to save output PDF
     * @return bool True on success, false on failure
     */
    public function convertImageToPdf(string $imagePath, string $outputPath): bool
    {
        try {
            // Validate input file exists
            if (! file_exists($imagePath)) {
                Log::error('Image file does not exist for PDF conversion', [
                    'path' => $imagePath,
                ]);

                return false;
            }

            $imagick = new Imagick;
            $imagick->setResolution(300, 300); // Max quality per requirement #5

            // Handle PDF sources (read first page only)
            if (str_ends_with(strtolower($imagePath), '.pdf')) {
                $imagick->readImage($imagePath.'[0]');
            } else {
                $imagick->readImage($imagePath);
            }

            // Get original image dimensions
            $imageWidth = $imagick->getImageWidth();
            $imageHeight = $imagick->getImageHeight();

            // A4 dimensions at 300 DPI (210mm x 297mm = 2480 x 3508 pixels)
            $a4Width = 2480;
            $a4Height = 3508;

            // Calculate scaling to fit A4 while preserving aspect ratio
            $scaleWidth = $a4Width / $imageWidth;
            $scaleHeight = $a4Height / $imageHeight;
            $scale = min($scaleWidth, $scaleHeight);

            // Resize to fit A4
            $newWidth = (int) ($imageWidth * $scale);
            $newHeight = (int) ($imageHeight * $scale);

            $imagick->resizeImage(
                $newWidth,
                $newHeight,
                Imagick::FILTER_LANCZOS,
                1
            );

            // Create white A4 canvas
            $canvas = new Imagick;
            $canvas->newImage($a4Width, $a4Height, 'white', 'pdf');

            // Center image horizontally, top-align vertically
            $x = (int) (($a4Width - $newWidth) / 2);
            $y = 0;

            $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, $x, $y);

            // Apply compression for ~1MB target file size
            $canvas->setImageCompressionQuality(85); // Balance quality vs size
            $canvas->setImageCompression(Imagick::COMPRESSION_JPEG);

            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Save PDF
            $canvas->writeImage($outputPath);

            // Get final file size
            $fileSizeKB = round(filesize($outputPath) / 1024);

            // Cleanup Imagick objects
            $imagick->clear();
            $imagick->destroy();
            $canvas->clear();
            $canvas->destroy();

            Log::info('Image converted to PDF using Imagick', [
                'input' => basename($imagePath),
                'output' => basename($outputPath),
                'size_kb' => $fileSizeKB,
                'original_dimensions' => "{$imageWidth}x{$imageHeight}",
                'scaled_dimensions' => "{$newWidth}x{$newHeight}",
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Imagick image-to-PDF conversion failed', [
                'input' => $imagePath,
                'output' => $outputPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Convert HTML to PDF using DomPDF.
     *
     * Strategy:
     * - A4 paper format
     * - Configurable margins (default 20mm all sides)
     * - Built-in compression enabled
     * - Remote resources disabled for security
     *
     * @param  string  $html  HTML content to convert
     * @param  string  $outputPath  Path to save output PDF
     * @param  array  $options  Optional settings (margins, etc.)
     * @return bool True on success, false on failure
     */
    public function convertHtmlToPdf(string $html, string $outputPath, array $options = []): bool
    {
        try {
            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', false) // Security: disable remote resources
                ->setOption('compress', 1) // Enable PDF compression
                ->setOption('dpi', 96) // Lower DPI for HTML (still readable)
                ->setOption('margin-top', $options['margin-top'] ?? 20)
                ->setOption('margin-right', $options['margin-right'] ?? 20)
                ->setOption('margin-bottom', $options['margin-bottom'] ?? 20)
                ->setOption('margin-left', $options['margin-left'] ?? 20);

            $pdf->save($outputPath);

            // Get final file size
            $fileSizeKB = round(filesize($outputPath) / 1024);

            Log::info('HTML converted to PDF using DomPDF', [
                'output' => basename($outputPath),
                'size_kb' => $fileSizeKB,
                'html_length' => strlen($html),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('DomPDF HTML-to-PDF conversion failed', [
                'output' => $outputPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Download image from URL and convert to PDF.
     *
     * Strategy:
     * - Download with retry logic (max 3 attempts)
     * - Exponential backoff on failures
     * - Temp file cleanup guaranteed
     * - 30s HTTP timeout
     *
     * @param  string  $imageUrl  URL of image to download
     * @param  string  $outputPath  Path to save output PDF
     * @param  int  $maxRetries  Maximum retry attempts (default 3)
     * @return bool True on success, false on failure
     */
    public function downloadAndConvertToPdf(string $imageUrl, string $outputPath, int $maxRetries = 3): bool
    {
        $tempPath = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Download image with timeout
                $response = Http::timeout(30)->get($imageUrl);

                if (! $response->successful()) {
                    Log::warning("Failed to download image (attempt {$attempt}/{$maxRetries})", [
                        'url' => $imageUrl,
                        'status' => $response->status(),
                    ]);

                    if ($attempt < $maxRetries) {
                        sleep(2 * $attempt); // Exponential backoff

                        continue;
                    }

                    return false;
                }

                // Save to temp file
                $tempPath = storage_path('app/private/temp/'.uniqid('download_').'.tmp');

                // Ensure temp directory exists
                $tempDir = dirname($tempPath);
                if (! is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                file_put_contents($tempPath, $response->body());

                // Convert to PDF
                $result = $this->convertImageToPdf($tempPath, $outputPath);

                // Cleanup temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

                if ($result) {
                    Log::info('Downloaded and converted image to PDF', [
                        'url' => $imageUrl,
                        'output' => basename($outputPath),
                        'attempts' => $attempt,
                    ]);
                }

                return $result;

            } catch (Exception $e) {
                Log::warning("Error downloading/converting image (attempt {$attempt}/{$maxRetries})", [
                    'url' => $imageUrl,
                    'error' => $e->getMessage(),
                ]);

                // Cleanup temp file on exception
                if ($tempPath && file_exists($tempPath)) {
                    unlink($tempPath);
                }

                if ($attempt < $maxRetries) {
                    sleep(2 * $attempt); // Exponential backoff

                    continue;
                }

                Log::error("Failed to download and convert image after {$maxRetries} attempts", [
                    'url' => $imageUrl,
                ]);

                return false;
            }
        }

        return false;
    }
}
