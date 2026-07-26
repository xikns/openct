<?php
// includes/db.php
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    header('Location: install.php');
    exit;
}
require_once $configFile;
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}
?>