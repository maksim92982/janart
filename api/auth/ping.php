<?php
require_once __DIR__ . '/../../lib/auth-bootstrap.php';

auth_handle_preflight();

auth_send_json([
    'ok' => true,
    'php' => PHP_VERSION,
    'time' => date('c'),
]);


