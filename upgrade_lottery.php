<?php
// upgrade_penalty.php - 惩罚抽奖模块数据库升级
if (!file_exists(__DIR__ . '/includes/config.php')) {
    die('❌ 系统尚未安装，请先运行 install.php。');
}
require_once __DIR__ . '/includes/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die('❌ 数据库连接失败：' . $e->getMessage());
}

$messages = [];

// 惩罚奖品表
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `penalty_prizes` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(100) NOT NULL,
      `probability` INT NOT NULL DEFAULT 10,
      `total` INT NOT NULL DEFAULT 100,
      `drawn` INT NOT NULL DEFAULT 0,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $messages[] = '✅ 惩罚奖品表就绪';
} catch (Exception $e) { $messages[] = '❌ 惩罚奖品表创建失败：' . $e->getMessage(); }

// 惩罚记录表
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `penalty_records` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `prize_id` INT NOT NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $messages[] = '✅ 惩罚记录表就绪';
} catch (Exception $e) { $messages[] = '❌ 惩罚记录表创建失败：' . $e->getMessage(); }

// 新增系统配置
$configs = [
    'penalty_enabled' => '0',
    'penalty_count'   => '3',   // 默认最后3名
];
$stmtCheck = $pdo->prepare("SELECT config_key FROM config WHERE config_key = ?");
$stmtInsert = $pdo->prepare("INSERT INTO config (config_key, config_value) VALUES (?, ?)");
foreach ($configs as $key => $value) {
    $stmtCheck->execute([$key]);
    if (!$stmtCheck->fetch()) {
        $stmtInsert->execute([$key, $value]);
        $messages[] = "✅ 新增配置 {$key} = {$value}";
    } else {
        $messages[] = "ℹ️ 配置 {$key} 已存在，跳过";
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>惩罚抽奖升级</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, sans-serif; max-width: 600px; margin: 50px auto; padding: 0 20px; }
        li { margin: 8px 0; }
    </style>
</head>
<body>
    <h2>🎁 惩罚抽奖模块升级</h2>
    <ul><?php foreach ($messages as $msg): ?><li><?= $msg ?></li><?php endforeach; ?></ul>
    <p style="color:#2ecc71;">✨ 升级完成！请立即删除本文件。</p>
    <p><a href="index.php">→ 返回首页</a></p>
</body>
</html>