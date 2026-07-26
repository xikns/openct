<?php
// upgrade_security.php - 添加保密问题字段
if (!file_exists(__DIR__ . '/includes/config.php')) {
    die('❌ 系统尚未安装');
}
require_once __DIR__ . '/includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die('❌ 数据库连接失败：' . $e->getMessage());
}

$messages = [];

// 检查并添加字段
$columns = [
    'secret_question'    => "VARCHAR(255) DEFAULT NULL COMMENT '保密问题'",
    'secret_answer_hash' => "VARCHAR(255) DEFAULT NULL COMMENT '保密问题答案哈希'"
];

foreach ($columns as $col => $def) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
    $stmt->execute([$col]);
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN `$col` $def");
        $messages[] = "✅ 已添加字段 {$col}";
    } else {
        $messages[] = "ℹ️ 字段 {$col} 已存在，跳过";
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>升级</title></head>
<body>
    <h3>数据库升级结果</h3>
    <ul><?php foreach ($messages as $msg): ?><li><?= $msg ?></li><?php endforeach; ?></ul>
    <p style="color:green;">✨ 完成！请立即删除本文件。</p>
</body>
</html>