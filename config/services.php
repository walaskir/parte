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

    'parte' => [
        'extract_portraits' => env('EXTRACT_PORTRAITS', true),
    ],

];
