<?php
require_once 'includes/header.php';
if (!$isTeacher) die('无权访问');

try {
    // 1. 清理重复配置，只保留每条配置最新的一条记录
    $pdo->exec("DELETE t1 FROM config t1 INNER JOIN config t2 WHERE t1.id < t2.id AND t1.config_key = t2.config_key");

    // 2. 彻底重置抽奖开关和积分数值，避免脏数据
    $pdo->exec("INSERT INTO config (config_key, config_value) VALUES ('lottery_enabled', '0') ON DUPLICATE KEY UPDATE config_value = '0'");
    $pdo->exec("INSERT INTO config (config_key, config_value) VALUES ('lottery_cost', '10') ON DUPLICATE KEY UPDATE config_value = '10'");

    echo "<div style='padding:40px; font-family: sans-serif; text-align:center;'>";
    echo "<h2 style='color:#22c55e;'>✅ 数据库修复成功！</h2>";
    echo "<p style='color:#6b7280;'>重复的抽奖配置已清理完毕，现在开关可以正常保存了。</p>";
    echo "<a href='/admin/lottery.php' style='display: inline-block; margin-top:20px; padding:10px 25px; background:#4f46e5; color:white; border-radius:8px; text-decoration:none;'>👉 立即前往后台设置</a>";
    echo "</div>";
} catch (Exception $e) {
    echo "修复失败（可能数据库结构不同）：" . $e->getMessage();
}
?>