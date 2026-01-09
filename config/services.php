<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'vision' => [
        // DEPRECATED - Will throw exception if used in production
        'provider' => env('VISION_PROVIDER'),
        'fallback_provider' => env('VISION_FALLBACK_PROVIDER'),

        // NEW configuration (required)
        'text_provider' => env('VISION_TEXT_PROVIDER'),
        'text_fallback' => env('VISION_TEXT_FALLBACK'),
        'photo_provider' => env('VISION_PHOTO_PROVIDER'),
        'photo_fallback' => env('VISION_PHOTO_FALLBACK'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3-flash-preview'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'zhipuai' => [
        'api_key' => env('ZHIPUAI_API_KEY'),
        'model' => env('ZHIPUAI_MODEL', 'glm-4.6v-flash'),
        'base_url' => env('ZHIPUAI_BASE_URL', 'https://open.bigmodel.cn/api/paas/v4'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 2048),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    'abacusai' => [
        'api_key' => env('ABACUSAI_API_KEY'),
        'base_url' => env('ABACUSAI_BASE_URL', 'https://routellm.abacus.ai'),
        'models' => [
            'gemini-3-flash' => 'GEMINI-3-FLASH-PREVIEW',
            'claude-sonnet-4.5' => 'CLAUDE-SONNET-4-5-20250929',
            'gemini-2.5-pro' => 'GEMINI-2.5-PRO',
            'gpt-5.2' => 'GPT-5.2',
        ],
    ],

    'parte' => [
        'extract_portraits' => env('EXTRACT_PORTRAITS', true),
    ],

];
