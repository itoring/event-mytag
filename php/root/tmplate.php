<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/db.php';
require_once __DIR__ . '/app/lib/auth.php';

require_login(); // 未ログインならloginへ

$pdo = db();
$userId = current_user_id();
