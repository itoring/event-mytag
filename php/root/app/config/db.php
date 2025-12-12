<?php
declare(strict_types=1);

/**
 * DB接続（PDO）
 * 使い方: $pdo = db();
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // ====== ここをConoHaのDB情報に合わせて変更 ======
    $dbHost = 'localhost';
    $dbName = 'YOUR_DB_NAME';
    $dbUser = 'YOUR_DB_USER';
    $dbPass = 'YOUR_DB_PASSWORD';
    // ==============================================

    $charset = 'utf8mb4';
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    return $pdo;
}
