# Portrait Extraction Quality Report

**Test Date:** January 8, 2026  
**Total Portraits Extracted:** 8 (4 per document)  
**Success Rate:** 87.5% (7/8 good quality)

---

## Quality Summary

| Portrait                   | Size    | Dimensions | Quality          | Issue            |
| -------------------------- | ------- | ---------- | ---------------- | ---------------- |
| **Raszka/Gemini 3 Flash**  | 1.8 KB  | 400x400    | ⚠️ **TOO SMALL** | Pixel coords bug |
| Raszka/Claude Sonnet 4.5   | 33.1 KB | 302x400    | ✅ Excellent     | None             |
| Raszka/Gemini 2.5 Pro      | 22.7 KB | 219x400    | ✅ Good          | None             |
| Raszka/GPT-5.2             | 23.9 KB | 210x400    | ✅ Good          | None             |
| **Wilhelm/Gemini 3 Flash** | 38.1 KB | 298x400    | ✅ Good          | None             |
| Wilhelm/Claude Sonnet 4.5  | 17.5 KB | 167x400    | ✅ Good          | None             |
| Wilhelm/Gemini 2.5 Pro     | 22.8 KB | 238x400    | ✅ Good          | None             |
| Wilhelm/GPT-5.2            | 18.6 KB | 217x400    | ✅ Good          | None             |

---

## Critical Issue: Gemini 3 Flash Coordinate Bug

### Raszka Document (FAILED)

**Bounding Box Returned:**

```json
{
    "x": 422,
    "y": 100,
    "width": 170,
    "height": 158
}
```

**Coordinate System:** PIXELS (image is 2458x3488px)

**Actual Percentages:**

- x: 422 / 2458 = 17.2% (NOT 422%)
- y: 100 / 3488 = 2.9% (NOT 100%)
- width: 170 / 2458 = 6.9%
- height: 158 / 3488 = 4.5%

**Result:** Portrait cropped from wrong area → 400x400px of mostly background → 1.8 KB file

---

### Wilhelm Document (SUCCESS)

**Bounding Box Returned:**

```json
{
    "x": 40.8,
    "y": 10.8,
    "width": 16.6,
    "height": 15.7
}
```

**Coordinate System:** PERCENTAGES (correct!)

**Result:** Portrait correctly cropped → 298x400px of actual portrait → 38.1 KB file

---

## Root Cause Analysis

**Why did Gemini 3 Flash behave inconsistently?**

1. **Same model, different outputs** - Both tests used `GEMINI-3-FLASH-PREVIEW`
2. **Same image specs** - Both 2458x3488px @ 300 DPI JPEG
3. **Same prompt** - Identical detection request
4. **Different dates** - Raszka tested earlier, Wilhelm tested later

**Hypothesis:** Abacus.AI API may have:

- Updated coordinate format between tests
- Different behavior based on image content
- Inconsistent model version routing

---

## Comparison with Other Models

### Coordinate System Consistency

| Model             | Raszka Coords            | Wilhelm Coords            | Consistent? |
| ----------------- | ------------------------ | ------------------------- | ----------- |
| Gemini 3 Flash    | Pixels (422, 100)        | Percentage (40.8%, 10.8%) | ❌ NO       |
| Claude Sonnet 4.5 | Percentage (39%, 10%)    | Percentage (45%, 14%)     | ✅ YES      |
| Gemini 2.5 Pro    | Percentage (42.1%, 9.7%) | Percentage (41.4%, 11.2%) | ✅ YES      |
| GPT-5.2           | Percentage (43.9%, 8.0%) | Percentage (34.9%, 7.7%)  | ✅ YES      |

**Conclusion:** Only Gemini 3 Flash has inconsistent behavior. All other models reliable.

---

## Portrait Quality Metrics

### File Size Distribution

```
< 5 KB:   1 portrait  (12.5%) ⚠️ TOO SMALL
5-20 KB:  2 portraits (25.0%) ✅ Acceptable
20-30 KB: 4 portraits (50.0%) ✅ Good
30-40 KB: 1 portrait  (12.5%) ✅ Excellent
```

**Optimal Range:** 15-40 KB (75% of portraits in this range)

---

### Dimensions Analysis

**Height:** All portraits scaled to 400px (consistent with PortraitExtractionService)

**Width Variation:**

- Min: 167px (Claude Sonnet 4.5 / Wilhelm) - Narrow portrait
- Max: 400px (Gemini 3 Flash / Raszka) - Square crop (WRONG)
- Average: 244px (excluding broken portrait)

**Aspect Ratios:**

- Claude Sonnet 4.5: 167:400 = 0.42 (narrow)
- Gemini 2.5 Pro: 228:400 = 0.57 (normal)
- GPT-5.2: 213:400 = 0.53 (normal)
- Gemini 3 Flash (Wilhelm): 298:400 = 0.75 (wide)

---

## Bounding Box Accuracy

### Raszka Portrait Location

| Model             | x       | y      | width  | height | Visual Check  |
| ----------------- | ------- | ------ | ------ | ------ | ------------- |
| Claude Sonnet 4.5 | 39%     | 10%    | 30%    | 28%    | ✅ Accurate   |
| Gemini 2.5 Pro    | 42.1%   | 9.7%   | 16.3%  | 20.9%  | ✅ Accurate   |
| GPT-5.2           | 43.9%   | 8.0%   | 14.2%  | 19.1%  | ✅ Accurate   |
| Gemini 3 Flash    | 17.2%\* | 2.9%\* | 6.9%\* | 4.5%\* | ❌ Wrong area |

\*Converted from pixels (422, 100, 170, 158)

---

### Wilhelm Portrait Location

| Model             | x     | y     | width | height | Visual Check |
| ----------------- | ----- | ----- | ----- | ------ | ------------ |
| Gemini 3 Flash    | 40.8% | 10.8% | 16.6% | 15.7%  | ✅ Accurate  |
| Claude Sonnet 4.5 | 45%   | 14%   | 16%   | 27%    | ✅ Accurate  |
| Gemini 2.5 Pro    | 41.4% | 11.2% | 17.8% | 21.1%  | ✅ Accurate  |
| GPT-5.2           | 34.9% | 7.7%  | 14.7% | 19.1%  | ✅ Accurate  |

**Observation:** All models agree portrait is around x=40%, y=10% (top-center area)

---

## Recommendations

### 1. Fix Coordinate Normalization (CRITICAL)

**Current Code:**

```php
// AbacusAiVisionService::extractPortrait() - BROKEN
$this->extractPortrait($imagePath, $bounds, $outputPath);
// Assumes $bounds are always percentages
```

**Fixed Code:**

```php
public function detectPortrait($imagePath, $model = self::GEMINI_3_FLASH): ?array
{
    // ... existing code ...

    // CRITICAL FIX: Normalize coordinates
    $imageInfo = getimagesize($imagePath);
    $imageWidth = $imageInfo[0];
    $imageHeight = $imageInfo[1];

    $normalizedBounds = $this->normalizeCoordinates(
        $bounds,
        $imageWidth,
        $imageHeight
    );

    return [
        'has_photo' => true,
        'bounds' => $normalizedBounds
    ];
}

private function normalizeCoordinates(
    array $bounds,
    int $imageWidth,
    int $imageHeight
): array {
    // If any coordinate > 100, assume pixels
    if ($bounds['x'] > 100 || $bounds['y'] > 100 ||
        $bounds['width'] > 100 || $bounds['height'] > 100) {

        Log::warning('Abacus.AI returned pixel coordinates, converting to percentages', [
            'original' => $bounds,
            'image_size' => "{$imageWidth}x{$imageHeight}"
        ]);

        return [
            'x' => round(($bounds['x'] / $imageWidth) * 100, 2),
            'y' => round(($bounds['y'] / $imageHeight) * 100, 2),
            'width' => round(($bounds['width'] / $imageWidth) * 100, 2),
            'height' => round(($bounds['height'] / $imageHeight) * 100, 2),
        ];
    }

    return $bounds; // Already percentages
}
```

---

### 2. Add Quality Validation

```php
public function validatePortraitQuality(string $portraitPath): array
{
    if (!file_exists($portraitPath)) {
        return ['valid' => false, 'reason' => 'File not found'];
    }

    $size = filesize($portraitPath);
    $imageInfo = getimagesize($portraitPath);

    if (!$imageInfo) {
        return ['valid' => false, 'reason' => 'Invalid image'];
    }

    [$width, $height] = $imageInfo;

    // Quality checks
    if ($size < 5000) {
        return ['valid' => false, 'reason' => 'File too small (< 5 KB)', 'size' => $size];
    }

    if ($width < 100 || $height < 100) {
        return ['valid' => false, 'reason' => 'Dimensions too small', 'size' => "{$width}x{$height}"];
    }

    if ($width === $height && $size < 10000) {
        return ['valid' => false, 'reason' => 'Square crop (likely wrong area)', 'size' => "{$width}x{$height}"];
    }

    return [
        'valid' => true,
        'size' => $size,
        'dimensions' => "{$width}x{$height}",
        'quality' => $size > 30000 ? 'excellent' : ($size > 15000 ? 'good' : 'acceptable')
    ];
}
```

---

### 3. Add Retry Logic for Failed Portraits

```php
// In ExtractImageParteJob or DeathNoticeService
$portraitPath = /* ... extract portrait ... */;
$validation = $abacusService->validatePortraitQuality($portraitPath);

if (!$validation['valid']) {
    Log::warning('Portrait extraction failed, retrying with fallback model', [
        'reason' => $validation['reason'],
        'primary_model' => 'GEMINI-3-FLASH-PREVIEW'
    ]);

    // Retry with Claude Sonnet 4.5 (more reliable coordinates)
    $result = $abacusService->detectPortrait($imagePath, AbacusAiVisionService::CLAUDE_SONNET_4_5);
    $portraitPath = $abacusService->extractPortrait(/* ... */);
}
```

---

### 4. Monitor Coordinate System Usage

Add metrics to track which coordinate system is being used:

```php
// Log every coordinate normalization
if (/* pixel coordinates detected */) {
    Log::channel('metrics')->info('abacus_coordinate_normalization', [
        'model' => $model,
        'image_hash' => $deathNotice->hash,
        'original_system' => 'pixels',
        'normalized_to' => 'percentages'
    ]);
}
```

**Dashboard Query (Horizon/Logs):**

```sql
-- Count normalizations per model per day
SELECT
    DATE(created_at) as date,
    JSON_EXTRACT(context, '$.model') as model,
    COUNT(*) as normalizations
FROM logs
WHERE message = 'abacus_coordinate_normalization'
GROUP BY date, model
ORDER BY date DESC;
```

---

## Testing Plan

### Unit Tests

```php
// tests/Unit/AbacusAiVisionServiceTest.php

test('normalizes pixel coordinates to percentages', function () {
    $service = new AbacusAiVisionService();

    $pixelBounds = ['x' => 422, 'y' => 100, 'width' => 170, 'height' => 158];
    $imageWidth = 2458;
    $imageHeight = 3488;

    $normalized = $service->normalizeCoordinates($pixelBounds, $imageWidth, $imageHeight);

    expect($normalized['x'])->toBe(17.17); // 422/2458 * 100
    expect($normalized['y'])->toBe(2.87);  // 100/3488 * 100
});

test('preserves percentage coordinates', function () {
    $service = new AbacusAiVisionService();

    $percentBounds = ['x' => 40.8, 'y' => 10.8, 'width' => 16.6, 'height' => 15.7];
    $imageWidth = 2458;
    $imageHeight = 3488;

    $normalized = $service->normalizeCoordinates($percentBounds, $imageWidth, $imageHeight);

    expect($normalized)->toBe($percentBounds); // Unchanged
});
```

---

### Integration Tests

Test with BOTH documents:

```php
// tests/Feature/AbacusAiPortraitExtractionTest.php

test('extracts portraits from Raszka document with pixel coords', function () {
    $imagePath = 'public/parte/b8b1aab1fc52/parte_Raszka20260107_15163920-1.jpg';

    $service = new AbacusAiVisionService();
    $result = $service->detectPortrait($imagePath, AbacusAiVisionService::GEMINI_3_FLASH);

    expect($result['has_photo'])->toBeTrue();
    expect($result['bounds']['x'])->toBeLessThan(100); // Must be percentage
    expect($result['bounds']['y'])->toBeLessThan(100);
});

test('validates portrait quality after extraction', function () {
    // Extract portrait using Gemini 3 Flash
    $service = new AbacusAiVisionService();
    $portraitPath = '/tmp/test_portrait.jpg';

    $service->extractComplete('public/parte/b8b1aab1fc52/parte_Raszka20260107_15163920-1.jpg');

    $validation = $service->validatePortraitQuality($portraitPath);

    expect($validation['valid'])->toBeTrue();
    expect($validation['size'])->toBeGreaterThan(5000); // Not too small
});
```

---

## Success Criteria

Before deploying to production:

- ✅ All 8 test portraits >= 5 KB
- ✅ Coordinate normalization handles both pixels & percentages
- ✅ Unit tests pass for normalization logic
- ✅ Integration tests pass on Raszka document (known pixel coords)
- ✅ Re-extract Raszka portrait with fixed code → validate >= 15 KB
- ✅ No regressions on Wilhelm document (already working)

---

## Next Steps

1. **Implement coordinate normalization** in `AbacusAiVisionService.php` (app/Services/AbacusAiVisionService.php:48)
2. **Add quality validation method**
3. **Write unit tests** for normalization
4. **Write integration tests** for both documents
5. **Re-run portrait extraction** on Raszka with fixed code
6. **Verify all 8 portraits** >= 5 KB and correct dimensions
7. **Update FINAL_RECOMMENDATION.md** with test results

---

**Current Status:** 7/8 portraits good quality  
**Blocking Issue:** Coordinate system inconsistency  
**Resolution:** Implement normalization (2-3 hours development)  
**Test Suite:** 6 tests (3 unit + 3 integration)  
**Expected Outcome:** 8/8 portraits good quality after fix
