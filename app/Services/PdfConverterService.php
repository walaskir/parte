<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\PdfToImage\Pdf;

class PdfConverterService
{
    /**
     * Convert PDF first page to JPG with specified DPI for OCR
     */
    public function convertToJpg(string $pdfPath, string $outputPath, int $dpi = 300): bool
    {
        try {
            if (! file_exists($pdfPath)) {
                Log::error('PDF file not found for conversion', ['path' => $pdfPath]);

                return false;
            }

            $pdf = new Pdf($pdfPath);
            $pdf->resolution($dpi)
                ->format(\Spatie\PdfToImage\Enums\OutputFormat::Jpg)
                ->save($outputPath);

            if (! file_exists($outputPath)) {
                Log::error('PDF to JPG conversion failed - output not created', [
                    'pdf' => $pdfPath,
                    'output' => $outputPath,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('PDF to JPG conversion exception', [
                'pdf' => $pdfPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Check if PDF conversion is available (Imagick or Ghostscript)
     */
    public function isAvailable(): bool
    {
        // Check Imagick extension
        if (extension_loaded('imagick')) {
            return true;
        }

        // Check Ghostscript binary
        exec('gs --version 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Validate that path is within allowed directories for security
     */
    private function validatePath(string $path): bool
    {
        $realPath = realpath(dirname($path));
        if ($realPath === false) {
            return false;
        }

        $allowedPaths = [
            storage_path('app/parte'),
            storage_path('app/temp'),
        ];

        foreach ($allowedPaths as $allowed) {
            $allowedReal = realpath($allowed);
            if ($allowedReal && str_starts_with($realPath, $allowedReal)) {
                return true;
            }
        }

        return false;
    }
}
