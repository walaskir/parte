<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class PortraitExtractionService
{
    /**
     * Extract portrait from parte image using bounding box coordinates.
     *
     * @param  string  $imagePath  Original parte image (JPG/PNG/PDF)
     * @param  array  $bbox  ['x_percent', 'y_percent', 'width_percent', 'height_percent']
     * @return string|null Path to extracted portrait JPG, or null on failure
     */
    public function extractPortrait(string $imagePath, array $bbox): ?string
    {
        try {
            // Validate bounding box
            if (! $this->validateBbox($bbox)) {
                Log::warning('Invalid bounding box provided for portrait extraction', [
                    'image' => $imagePath,
                    'bbox' => $bbox,
                ]);

                return null;
            }

            // Apply automatic padding to remove black borders
            $adjustedBbox = $this->applyAutomaticPadding($bbox);

            Log::info('Applying automatic padding to bbox', [
                'original' => $bbox,
                'adjusted' => $adjustedBbox,
            ]);

            // Check if image exists
            if (! file_exists($imagePath)) {
                Log::warning('Image file does not exist for portrait extraction', [
                    'image' => $imagePath,
                ]);

                return null;
            }

            // Load image with Imagick
            $imagick = new \Imagick;
            $imagick->setResolution(300, 300); // High resolution for quality

            // Handle PDF files (read first page only)
            if (str_ends_with(strtolower($imagePath), '.pdf')) {
                $imagick->readImage($imagePath.'[0]');
            } else {
                $imagick->readImage($imagePath);
            }

            // Get actual image dimensions
            $imageWidth = $imagick->getImageWidth();
            $imageHeight = $imagick->getImageHeight();

            // Convert percentages to pixels (using adjusted bbox)
            $x = (int) (($adjustedBbox['x_percent'] / 100) * $imageWidth);
            $y = (int) (($adjustedBbox['y_percent'] / 100) * $imageHeight);
            $width = (int) (($adjustedBbox['width_percent'] / 100) * $imageWidth);
            $height = (int) (($adjustedBbox['height_percent'] / 100) * $imageHeight);

            // Validate pixel coordinates don't exceed image bounds
            if ($x + $width > $imageWidth) {
                $width = $imageWidth - $x;
            }
            if ($y + $height > $imageHeight) {
                $height = $imageHeight - $y;
            }

            // Ensure minimum size (at least 50x50px after calculation)
            if ($width < 50 || $height < 50) {
                Log::warning('Portrait region too small after calculation', [
                    'calculated_width' => $width,
                    'calculated_height' => $height,
                ]);
                $imagick->clear();
                $imagick->destroy();

                return null;
            }

            // Crop the portrait region
            $imagick->cropImage($width, $height, $x, $y);

            // Resize to max 400x400 (preserve aspect ratio)
            $maxSize = 400;
            if ($width > $maxSize || $height > $maxSize) {
                $imagick->thumbnailImage($maxSize, $maxSize, true);
            }

            // Convert to JPEG format
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85);

            // Save to temporary file
            $tempPath = storage_path('app/temp/portrait_'.uniqid().'.jpg');

            // Ensure temp directory exists
            $tempDir = dirname($tempPath);
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $imagick->writeImage($tempPath);

            // Cleanup Imagick
            $imagick->clear();
            $imagick->destroy();

            Log::info('Portrait extracted successfully', [
                'source_image' => basename($imagePath),
                'portrait_path' => $tempPath,
                'bbox' => $bbox,
                'final_size' => filesize($tempPath).' bytes',
            ]);

            return $tempPath;

        } catch (Exception $e) {
            Log::error('Portrait extraction failed', [
                'image' => $imagePath,
                'bbox' => $bbox,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Validate bounding box has required fields and reasonable values.
     *
     * @param  array  $bbox  Bounding box array to validate
     * @return bool True if valid, false otherwise
     */
    private function validateBbox(array $bbox): bool
    {
        // Check required fields exist
        $requiredFields = ['x_percent', 'y_percent', 'width_percent', 'height_percent'];
        foreach ($requiredFields as $field) {
            if (! isset($bbox[$field])) {
                return false;
            }
        }

        // Check all values are numeric and within 0-100 range
        if ($bbox['x_percent'] < 0 || $bbox['x_percent'] > 100 ||
            $bbox['y_percent'] < 0 || $bbox['y_percent'] > 100 ||
            $bbox['width_percent'] < 0 || $bbox['width_percent'] > 100 ||
            $bbox['height_percent'] < 0 || $bbox['height_percent'] > 100) {
            return false;
        }

        // Check width and height are at least 5% (too small = probably error)
        if ($bbox['width_percent'] < 5 || $bbox['height_percent'] < 5) {
            return false;
        }

        // Check x + width doesn't exceed 100%
        if ($bbox['x_percent'] + $bbox['width_percent'] > 100) {
            return false;
        }

        // Check y + height doesn't exceed 100%
        if ($bbox['y_percent'] + $bbox['height_percent'] > 100) {
            return false;
        }

        return true;
    }

    /**
     * Apply automatic padding to remove black borders from portrait bounding box.
     *
     * Strategy:
     * - side=1%, bottom=1% for all (consistent border removal)
     * - top=1% only if Y < 8% (photo positioned high = likely has black bar on top)
     *
     * @param  array  $bbox  Original bounding box ['x_percent', 'y_percent', 'width_percent', 'height_percent']
     * @return array Adjusted bounding box with padding applied
     */
    private function applyAutomaticPadding(array $bbox): array
    {
        // Determine padding based on photo position
        $topPadding = ($bbox['y_percent'] < 8.0) ? 1.0 : 0.0;
        $sidePadding = 1.0;
        $bottomPadding = 1.0;

        // Calculate adjusted coordinates
        $adjusted = [
            'x_percent' => $bbox['x_percent'] + $sidePadding,
            'y_percent' => $bbox['y_percent'] + $topPadding,
            'width_percent' => $bbox['width_percent'] - (2 * $sidePadding),
            'height_percent' => $bbox['height_percent'] - ($topPadding + $bottomPadding),
        ];

        // Ensure adjusted bbox stays within valid range (0-100%)
        $adjusted['x_percent'] = max(0, min(100, $adjusted['x_percent']));
        $adjusted['y_percent'] = max(0, min(100, $adjusted['y_percent']));
        $adjusted['width_percent'] = max(0, min(100, $adjusted['width_percent']));
        $adjusted['height_percent'] = max(0, min(100, $adjusted['height_percent']));

        // Ensure x + width doesn't exceed 100%
        if ($adjusted['x_percent'] + $adjusted['width_percent'] > 100) {
            $adjusted['width_percent'] = 100 - $adjusted['x_percent'];
        }

        // Ensure y + height doesn't exceed 100%
        if ($adjusted['y_percent'] + $adjusted['height_percent'] > 100) {
            $adjusted['height_percent'] = 100 - $adjusted['y_percent'];
        }

        return $adjusted;
    }
}
