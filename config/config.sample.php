<?php
// Normally the installer creates config/config.php for you.
// Only copy this file to config.php and edit it if you are restoring a system by hand.
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'teams',
        'user' => 'root',
        'pass' => '',          // XAMPP default is an empty password
    ],
    'max_upload_mb' => 8,      // per-file limit for documents and photos
];
