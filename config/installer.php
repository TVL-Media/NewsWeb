<?php

return [
    // Written when installation finishes: {"version": "...", "installed_at": "..."}. While it is
    // missing (and no existing install is found in the database) every page goes to /install.
    'lock_path' => env('INSTALLER_LOCK_PATH', storage_path('installed')),

    // The .env file the installer writes.
    'env_path' => env('INSTALLER_ENV_PATH', base_path('.env')),

    // Tests run as an installed app (phpunit.xml sets this).
    'assume_installed' => (bool) env('INSTALLER_ASSUME_INSTALLED', false),

    // Optional Envato purchase-code step. Off unless ENVATO_LICENSE_CHECK=true.
    'envato' => [
        'enabled' => (bool) env('ENVATO_LICENSE_CHECK', false),
        'token' => env('ENVATO_API_TOKEN'),     // personal token with "View your items' sales history"
        'item_id' => env('ENVATO_ITEM_ID'),     // optional: the code must be for this item
    ],

    'php' => '8.3.0',

    // Required PHP extensions.
    'extensions' => ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml'],

    // Nice to have: shown as warnings, never block the install.
    'recommended' => [
        'intl' => 'Better number and date formatting',
        'imagick' => 'Share images render Hindi letters perfectly (GD is used otherwise)',
        'zip' => 'Needed by some update packages',
    ],

    // Folders the web server must be able to write to (relative to the project root).
    'writable' => ['storage', 'storage/app', 'storage/app/public', 'storage/framework', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'],
];
