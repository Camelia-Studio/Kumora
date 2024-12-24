<?php
// config.php
return [
    'db' => [
        'path' => __DIR__ . '/database.sqlite'
    ],
    'security' => [
        'session_duration' => 3600,
        'max_login_attempts' => 3,
        'attempt_window' => 1800 // 30 minutes
    ],
    'roles' => [
        'admin' => [
            'upload' => true,
            'download' => true,
            'delete' => true,
            'rename' => true,
            'view_logs' => true
        ],
        'user' => [
            'upload' => true,
            'download' => true,
            'delete' => false,
            'rename' => false,
            'view_logs' => false
        ],
        'visitor' => [
            'upload' => false,
            'download' => true,
            'delete' => false,
            'rename' => false,
            'view_logs' => false
        ]
    ]
];
?>
