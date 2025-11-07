<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Yandex Lockbox API Token
    |--------------------------------------------------------------------------
    |
    | Your OAuth or IAM token for authentication with Yandex Cloud.
    | 
    | RECOMMENDED: Use OAuth token (starts with y0_, y1_, y2_, y3_)
    | - Get OAuth token: https://yandex.cloud/ru/docs/iam/concepts/authorization/oauth-token
    | - OAuth tokens don't expire and are automatically converted to IAM tokens
    | 
    | ALTERNATIVE: Use IAM token (starts with t1.)
    | - Get IAM token: yc iam create-token
    | - IAM tokens expire after 12 hours
    |
    */
    'token' => env('YANDEX_LOCKBOX_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Yandex Lockbox API Base URI
    |--------------------------------------------------------------------------
    */
    'base_uri' => env('YANDEX_LOCKBOX_BASE_URI', 'https://lockbox.api.cloud.yandex.net/lockbox/v1'),

    /*
    |--------------------------------------------------------------------------
    | Default Folder ID
    |--------------------------------------------------------------------------
    |
    | Optional default folder ID for listing secrets
    |
    */
    'default_folder_id' => env('YANDEX_LOCKBOX_FOLDER_ID', ''),
];