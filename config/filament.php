<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Panel Emails
    |--------------------------------------------------------------------------
    |
    | List of email addresses that are allowed to access the Filament admin
    | panel. Users with these emails will pass the canAccessPanel() check.
    |
    */
    'admin_emails' => array_filter(array_map(
        'trim',
        explode(',', env('FILAMENT_ADMIN_EMAILS', 'robert@walaski.cz'))
    )),
];
