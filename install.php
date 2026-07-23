<?php
// install.php - 班级积分管理系统 安装引导（已修复外键冲突）
session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// 如果已安装，跳转首页
if (file_exists(__DIR__ . '/includes/config.php')) {
    header('Location: index.php');
    exit;
}

// 处理表单
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        $host = trim($_POST['host'] ?? '');
        $dbname = trim($_POST['dbname'] ?? '');
        $dbuser = trim($_POST['dbuser'] ?? '');
        $dbpass = $_POST['dbpass'] ?? '';
        if (empty($host) || empty($dbname) || empty($dbuser)) {
            $error = '请填写完整的数据库信息。';
        } else {
            try {
                $dsn = "mysql:host={$host};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                // 创建数据库（如果不存在）
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $_SESSION['install_db'] = compact('host', 'dbname', 'dbuser', 'dbpass');
                header('Location: install.php?step=2');
                exit;
            } catch (PDOException $e) {
                $error = '数据库连接失败：' . $e->getMessage();
            }
        }
    } elseif ($step == 2 && isset($_SESSION['install_db'])) {
        $admin_user = trim($_POST['admin_user'] ?? '');
        $admin_pass = $_POST['admin_pass'] ?? '';
        $admin_name = trim($_POST['admin_name'] ?? '');
        if (empty($admin_user) || empty($admin_pass) || empty($admin_name)) {
            $error = '请填写完整的管理员信息。';
        } elseif (strlen($admin_pass) < 6) {
            $error = '密码长度至少6位。';
        } else {
            try {
                $db = $_SESSION['install_db'];
                $pdo = new PDO(
                    "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8mb4",
                    $db['dbuser'],
                    $db['dbpass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                // ----- 关键修复：禁用外键检查，避免其他表的外键影响本系统表的重建 -----
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

                // 先删除可能存在的旧表（本系统的四张表）
                $pdo->exec("
                    DROP TABLE IF EXISTS `points_log`;
                    DROP TABLE IF EXISTS `slides`;
                    DROP TABLE IF EXISTS `config`;
                    DROP TABLE IF EXISTS `users`;
                ");

                // 创建全新的表
                $pdo->exec("
                    CREATE TABLE `users` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '学号',
                        `realname` VARCHAR(50) NOT NULL COMMENT '姓名',
                        `password` VARCHAR(255) NOT NULL,
                        `email` VARCHAR(100) DEFAULT NULL,
                        `role` ENUM('student','student_admin','teacher') NOT NULL DEFAULT 'student',
                        `points` INT NOT NULL DEFAULT 0,
                        `avatar` VARCHAR(255) DEFAULT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE `config` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `config_key` VARCHAR(50) UNIQUE NOT NULL,
                        `config_value` TEXT
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE `slides` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `image_url` VARCHAR(255) NOT NULL,
                        `sort_order` INT DEFAULT 0,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE `points_log` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NOT NULL,
                        `changed_points` INT NOT NULL,
                        `reason` VARCHAR(255) DEFAULT NULL,
                        `operator_id` INT NOT NULL,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                // 恢复外键检查
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                // 插入默认配置
                $pdo->exec("
                    INSERT INTO `config` (`config_key`, `config_value`) VALUES
                    ('class_name', '阳光一班'),
                    ('points_start', '2026-09-01 00:00:00'),
                    ('points_end', '2027-01-15 00:00:00'),
                    ('allow_register', '1'),
                    ('website_title', '班级积分管理系统')
                ");

                // 插入管理员
                $hashed = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `realname`, `password`, `role`) VALUES (?, ?, ?, 'teacher')");
                $stmt->execute([$admin_user, $admin_name, $hashed]);

                // 写入配置文件
                $configContent = "<?php\n"
                    . "define('DB_HOST', " . var_export($db['host'], true) . ");\n"
                    . "define('DB_NAME', " . var_export($db['dbname'], true) . ");\n"
                    . "define('DB_USER', " . var_export($db['dbuser'], true) . ");\n"
                    . "define('DB_PASS', " . var_export($db['dbpass'], true) . ");\n";
                file_put_contents(__DIR__ . '/includes/config.php', $configContent);

                // 创建上传目录
                if (!is_dir(__DIR__ . '/uploads/avatars')) {
                    mkdir(__DIR__ . '/uploads/avatars', 0755, true);
                }
                if (!is_dir(__DIR__ . '/uploads/slides')) {
                    mkdir(__DIR__ . '/uploads/slides', 0755, true);
                }

                unset($_SESSION['install_db']);
                $success = '安装成功！<a href="index.php">进入首页</a>';
                $step = 3; // 完成
            } catch (Exception $e) {
                // 发生异常时也要尝试恢复外键检查（避免遗留锁表状态）
                if (isset($pdo)) $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $error = '安装失败：' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安装向导 - 班级积分管理系统</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .install-box { background: white; border-radius: 2rem; padding: 2rem; box-shadow: 0 20px 60px rgba(0,0,0,0.1); max-width: 500px; width: 100%; }
    </style>
</head>
<body>
<div class="install-box">
    <h2 class="text-center mb-4">🚀 班级积分管理系统 安装</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($step == 1): ?>
        <form method="post">
            <div class="mb-3">
                <label>数据库主机</label>
                <input type="text" name="host" class="form-control" value="localhost" required>
            </div>
            <div class="mb-3">
                <label>数据库名</label>
                <input type="text" name="dbname" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>用户名</label>
                <input type="text" name="dbuser" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>密码</label>
                <input type="password" name="dbpass" class="form-control">
            </div>
            <button type="submit" class="btn btn-warning w-100 btn-lg">测试连接并继续</button>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="post">
            <h5>创建管理员账号</h5>
            <div class="mb-3">
                <label>账号（用户名）</label>
                <input type="text" name="admin_user" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>姓名</label>
                <input type="text" name="admin_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>密码</label>
                <input type="password" name="admin_pass" class="form-control" required minlength="6">
            </div>
            <button type="submit" class="btn btn-success w-100 btn-lg">完成安装</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>