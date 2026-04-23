<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Chart Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The disk used for storing chart PDFs and rendered images. In local
    | environments, this defaults to "local" for easier development.
    | In production, use "r2" for Cloudflare R2 storage.
    |
    */

    'chart' => env('CHART_FILESYSTEM_DISK', env('APP_ENV') === 'local' ? 'local' : 'r2'),

    /*
    |--------------------------------------------------------------------------
    | Audio Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The disk used for storing MP3 audio files attached to project songs.
    | In local environments, this defaults to "local" for easier development.
    | In production, use "r2" for Cloudflare R2 storage.
    |
    */

    'audio' => env('AUDIO_FILESYSTEM_DISK', env('APP_ENV') === 'local' ? 'local' : 'r2'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            'throw' => true,
            'report' => true,
        ],

        'r2-trash' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_TRASH_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | R2 Trash (Soft-Delete Redundancy)
    |--------------------------------------------------------------------------
    |
    | When enabled, delete operations copy files to a trash bucket before
    | removing them from the primary bucket. A scheduled cleanup command
    | permanently deletes expired trash after the retention period.
    |
    */

    'trash' => [
        'disk' => env('R2_TRASH_DISK', 'r2-trash'),
        'retention_days' => (int) env('R2_TRASH_RETENTION_DAYS', 30),
        'enabled' => (bool) env('R2_TRASH_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
