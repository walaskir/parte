<?php

namespace Tests\Feature;

use App\Services\VisionOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VisionOcrServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock config using NEW format (required)
        Config::set('services.vision.text_provider', 'gemini');
        Config::set('services.vision.text_fallback', 'zhipuai');
        Config::set('services.vision.photo_provider', 'gemini');
        Config::set('services.vision.photo_fallback', null);

        // Clear deprecated config to avoid warnings
        Config::set('services.vision.provider', null);
        Config::set('services.vision.fallback_provider', null);

        Config::set('services.gemini.api_key', 'test-gemini-key');
        Config::set('services.gemini.model', 'gemini-3-flash-preview');
        Config::set('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');

        Config::set('services.zhipuai.api_key', 'test-zhipuai-key');
        Config::set('services.zhipuai.model', 'glm-4.6v-flash');
        Config::set('services.zhipuai.base_url', 'https://open.bigmodel.cn/api/paas/v4');

        Config::set('services.anthropic.api_key', 'test-anthropic-key');
        Config::set('services.anthropic.model', 'claude-3-5-sonnet-20241022');
        Config::set('services.anthropic.max_tokens', 2048);
        Config::set('services.anthropic.version', '2023-06-01');
    }

    public function test_gemini_extraction_success(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => '{"full_name":"Jan Novák","death_date":"2026-01-01","funeral_date":"2026-01-05","announcement_text":"S bolestí v srdci oznamujeme, že dne 1. ledna 2026 nás navždy opustil pan Jan Novák. Rozloučení se koná v pátek 5. ledna 2026 ve 14:00 hodin v obřadní síni krematoria v Ostravě."}',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Create temporary test image
        $imagePath = storage_path('app/test_parte.jpg');
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $service = new VisionOcrService;
        $result = $service->extractTextFromImage($imagePath);

        $this->assertNotNull($result);
        $this->assertEquals('Jan Novák', $result['full_name']);
        $this->assertEquals('2026-01-01', $result['death_date']);
        $this->assertEquals('2026-01-05', $result['funeral_date']);
        $this->assertStringContainsString('S bolestí v srdci', $result['announcement_text']);

        // Cleanup
        @unlink($imagePath);
    }

    public function test_zhipuai_extraction_success(): void
    {
        Config::set('services.vision.text_provider', 'zhipuai');
        Config::set('services.vision.photo_provider', 'zhipuai');

        Http::fake([
            'open.bigmodel.cn/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"full_name":"Jan Novák","death_date":"2026-01-01","funeral_date":"2026-01-05","announcement_text":"S bolestí v srdci oznamujeme, že dne 1. ledna 2026 nás navždy opustil pan Jan Novák. Rozloučení se koná v pátek 5. ledna 2026 ve 14:00 hodin v obřadní síni krematoria v Ostravě."}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Create temporary test image
        $imagePath = storage_path('app/test_parte.jpg');
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $service = new VisionOcrService;
        $result = $service->extractTextFromImage($imagePath);

        $this->assertNotNull($result);
        $this->assertEquals('Jan Novák', $result['full_name']);
        $this->assertEquals('2026-01-01', $result['death_date']);
        $this->assertEquals('2026-01-05', $result['funeral_date']);
        $this->assertStringContainsString('S bolestí v srdci', $result['announcement_text']);

        // Cleanup
        @unlink($imagePath);
    }

    public function test_fallback_from_gemini_to_zhipuai(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'open.bigmodel.cn/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"full_name":"Marie Dvořáková","death_date":"2026-01-02","funeral_date":"2026-01-06","announcement_text":"Zemřela naše milovaná maminka."}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $imagePath = storage_path('app/test_parte.jpg');
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $service = new VisionOcrService;
        $result = $service->extractTextFromImage($imagePath);

        $this->assertNotNull($result);
        $this->assertEquals('Marie Dvořáková', $result['full_name']);
        $this->assertEquals('2026-01-02', $result['death_date']);

        @unlink($imagePath);
    }

    public function test_anthropic_fallback_when_zhipuai_fails(): void
    {
        Config::set('services.vision.text_provider', 'zhipuai');
        Config::set('services.vision.text_fallback', 'anthropic');
        Config::set('services.vision.photo_provider', 'zhipuai');

        Http::fake([
            'open.bigmodel.cn/*' => Http::response([], 500),
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['text' => '{"full_name":"Marie Dvořáková","death_date":"2026-01-02","funeral_date":"2026-01-06","announcement_text":"Zemřela naše milovaná maminka."}'],
                ],
            ], 200),
        ]);

        $imagePath = storage_path('app/test_parte.jpg');
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $service = new VisionOcrService;
        $result = $service->extractTextFromImage($imagePath);

        $this->assertNotNull($result);
        $this->assertEquals('Marie Dvořáková', $result['full_name']);
        $this->assertEquals('2026-01-02', $result['death_date']);

        @unlink($imagePath);
    }

    public function test_extraction_returns_null_when_all_apis_fail(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'open.bigmodel.cn/*' => Http::response([], 500),
            'api.anthropic.com/*' => Http::response([], 500),
        ]);

        $imagePath = storage_path('app/test_parte.jpg');
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $service = new VisionOcrService;
        $result = $service->extractTextFromImage($imagePath);

        $this->assertNull($result);

        @unlink($imagePath);
    }

    public function test_extraction_returns_null_for_nonexistent_image(): void
    {
        $service = new VisionOcrService;
        $result = $service->extractTextFromImage('/nonexistent/path/to/image.jpg');

        $this->assertNull($result);
    }

    public function test_gemini_handles_invalid_json_with_fallback(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'This is not valid JSON'],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'open.bigmodel.cn/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"full_name":"Marie Dvořáková","death_date":"2026-01-02"}',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $imagePath = storage_path('app/test_parte.jpg');
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $service = new VisionOcrService;
        $result = $service->extractTextFromImage($imagePath);

        $this->assertNotNull($result);
        $this->assertEquals('Marie Dvořáková', $result['full_name']);

        @unlink($imagePath);
    }

    public function test_tries_fallback_provider_when_primary_fails(): void
    {
        Config::set('services.vision.text_provider', 'gemini');
        Config::set('services.vision.text_fallback', 'anthropic');
        Config::set('services.vision.photo_provider', 'gemini');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['text' => '{"full_name":"Last Resort","death_date":"2026-01-03"}'],
                ],
            ], 200),
        ]);

        $imagePath = storage_path('app/test_parte.jpg');
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $service = new VisionOcrService;
        $result = $service->extractTextFromImage($imagePath);

        $this->assertNotNull($result);
        $this->assertEquals('Last Resort', $result['full_name']);

        @unlink($imagePath);
    }
}
