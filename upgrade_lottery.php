<?php
// upgrade_all.php - 班级积分管理系统 数据库完整升级（抽奖模块）
// 使用方法：上传到网站根目录，浏览器访问一次，看到成功提示后立即删除此文件。

// 1. 检查是否已安装
if (!file_exists(__DIR__ . '/includes/config.php')) {
    die('❌ 系统尚未安装，请先运行 install.php 完成安装。');
}
require_once __DIR__ . '/includes/config.php';

// 2. 连接数据库
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('❌ 数据库连接失败：' . $e->getMessage());
}

$messages = [];

// 3. 创建奖品表（积分抽奖）
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `prizes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `probability` INT NOT NULL DEFAULT 10 COMMENT '权重',
        `total` INT NOT NULL DEFAULT 100 COMMENT '总库存',
        `drawn` INT NOT NULL DEFAULT 0 COMMENT '已抽中数量',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $messages[] = '✅ 积分奖品表 (prizes) 就绪';
} catch (Exception $e) {
    $messages[] = '❌ 积分奖品表创建失败：' . $e->getMessage();
}

// 4. 创建惩罚奖品表
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `penalty_prizes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `probability` INT NOT NULL DEFAULT 10,
        `total` INT NOT NULL DEFAULT 100,
        `drawn` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $messages[] = '✅ 惩罚奖品表 (penalty_prizes) 就绪';
} catch (Exception $e) {
    $messages[] = '❌ 惩罚奖品表创建失败：' . $e->getMessage();
}

// 5. 创建抽奖记录表
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `lottery_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `prize_id` INT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $messages[] = '✅ 积分抽奖记录表 (lottery_records) 就绪';
} catch (Exception $e) {
    $messages[] = '❌ 积分抽奖记录表创建失败：' . $e->getMessage();
}

// 6. 创建惩罚记录表
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `penalty_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `prize_id` INT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $messages[] = '✅ 惩罚抽奖记录表 (penalty_records) 就绪';
} catch (Exception $e) {
    $messages[] = '❌ 惩罚抽奖记录表创建失败：' . $e->getMessage();
}

// 7. 补充配置项（如果缺失）
$configs = [
    'lottery_enabled' => '0',
    'lottery_cost'    => '10',
    'penalty_enabled' => '0',
    'penalty_count'   => '3',
];

$stmtCheck = $pdo->prepare("SELECT config_key FROM config WHERE config_key = ?");
$stmtInsert = $pdo->prepare("INSERT INTO config (config_key, config_value) VALUES (?, ?)");

foreach ($configs as $key => $value) {
    $stmtCheck->execute([$key]);
    if ($stmtCheck->fetch()) {
        $messages[] = "ℹ️ 配置 {$key} 已存在，跳过";
    } else {
        $stmtInsert->execute([$key, $value]);
        $messages[] = "✅ 新增配置 {$key} = {$value}";
    }
}

// 8. 输出结果
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>抽奖模块数据库升级</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, sans-serif; max-width: 600px; margin: 50px auto; padding: 0 20px; }
        li { margin: 8px 0; }
        .success { color: #2ecc71; }
    </style>
</head>
<body>
    <h2>🎁 抽奖模块数据库升级</h2>
    <ul>
        <?php foreach ($messages as $msg): ?>
            <li><?= htmlspecialchars($msg) ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="success">✨ 升级完成！请立即删除本文件（upgrade_all.php）以保证安全。</p>
    <p><a href="index.php">→ 返回首页</a></p>
</body>
</html>