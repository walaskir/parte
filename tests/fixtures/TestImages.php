<?php

namespace Tests\Fixtures;

/**
 * Helper class for managing test images in tests
 */
class TestImages
{
    /**
     * Get path to Raszka (Polish) test PDF
     */
    public static function getRaszkaPath(): string
    {
        return __DIR__.'/pdfs/parte_Raszka20260107_15163920-1.pdf';
    }

    /**
     * Get path to Wilhelm (Czech) test PDF
     */
    public static function getWilhelmPath(): string
    {
        return __DIR__.'/pdfs/Wilhelm20260105_09594623.pdf';
    }

    /**
     * Get base64-encoded 1x1 PNG for minimal tests (fallback)
     */
    public static function getTestBase64Png(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    /**
     * Create temporary test image
     *
     * @param  string  $sourceType  'base64' or 'raszka' or 'wilhelm'
     */
    public static function createTempImage(string $sourceType = 'base64'): string
    {
        $tempPath = storage_path('app/test_parte_'.uniqid().'.jpg');

        if ($sourceType === 'base64') {
            file_put_contents($tempPath, base64_decode(self::getTestBase64Png()));
        } elseif ($sourceType === 'raszka' && file_exists(self::getRaszkaPath())) {
            copy(self::getRaszkaPath(), $tempPath);
        } elseif ($sourceType === 'wilhelm' && file_exists(self::getWilhelmPath())) {
            copy(self::getWilhelmPath(), $tempPath);
        } else {
            // Fallback to base64
            file_put_contents($tempPath, base64_decode(self::getTestBase64Png()));
        }

        return $tempPath;
    }

    /**
     * Cleanup temporary image
     */
    public static function cleanup(string $path): void
    {
        if (file_exists($path) && str_starts_with($path, storage_path('app/test_parte_'))) {
            @unlink($path);
        }
    }

    /**
     * Check if real test PDFs are available
     */
    public static function hasRealTestData(): bool
    {
        return file_exists(self::getRaszkaPath()) && file_exists(self::getWilhelmPath());
    }
}
