<?php
declare(strict_types=1);
// Direct cPanel/Plesk fallback when URL rewriting is disabled.
$_SERVER['REQUEST_URI'] = '/install';
require __DIR__ . '/public/index.php';
