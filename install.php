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
                $success = '安装成功！<a href="index.php" style="color:#4f46e5;text-decoration:underline;">进入首页</a>';
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
    <style>
        /* 统一系统风格，摒弃渐变背景和Bootstrap */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background-color: #f5f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-wrapper {
            width: 100%;
            max-width: 440px;
        }
        .install-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 30px 35px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .install-box h2 {
            font-size: 20px;
            color: #1f2937;
            text-align: center;
            margin-bottom: 20px;
        }
        .install-box h5 {
            font-size: 15px;
            color: #374151;
            margin-bottom: 15px;
        }
        
        /* 进度条可视化组件 */
        .step-progress {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
        }
        .step-text {
            font-size: 13px;
            color: #6b7280;
            white-space: nowrap;
        }
        .step-track {
            flex: 1;
            height: 6px;
            background: #e8e8ed;
            border-radius: 4px;
            overflow: hidden;
        }
        .step-fill {
            height: 100%;
            background: #4f46e5;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* 信息提示框 */
        .msg-box {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid transparent;
        }
        .msg-error { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .msg-success { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }

        /* 表单元素 */
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
            background: white;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* 按钮 */
        .btn-install {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: none;
            background: #4f46e5;
            color: white;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 5px;
        }
        .btn-install:hover { background: #4338ca; }
        .btn-install:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .footer-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #6b7280;
        }
        .footer-link a { color: #4f46e5; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="install-wrapper">
    <div class="install-box">
        <h2>🚀 系统安装</h2>

        <!-- 可视化进度条 -->
        <div class="step-progress">
            <span class="step-text">
                <?php 
                    if ($step == 3) echo '安装完成';
                    else echo "步骤 {$step} / 2";
                ?>
            </span>
            <div class="step-track">
                <div class="step-fill" style="width: <?php echo ($step==3) ? '100%' : ($step*50) . '%'; ?>;"></div>
            </div>
        </div>

        <!-- 错误提示 -->
        <?php if ($error): ?>
            <div class="msg-box msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <!-- 成功提示 -->
        <?php if ($success): ?>
            <div class="msg-box msg-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <!-- 第一步：数据库连接 -->
            <form method="post">
                <div class="form-group">
                    <label>数据库主机</label>
                    <input type="text" name="host" class="form-control" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>数据库名</label>
                    <input type="text" name="dbname" class="form-control" placeholder="例如：class_points" required>
                </div>
                <div class="form-group">
                    <label>数据库用户名</label>
                    <input type="text" name="dbuser" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>数据库密码</label>
                    <input type="password" name="dbpass" class="form-control">
                </div>
                <button type="submit" class="btn-install">连接并继续</button>
            </form>
        <?php elseif ($step == 2): ?>
            <!-- 第二步：创建管理员账号 -->
            <h5>创建管理员账号</h5>
            <form method="post">
                <div class="form-group">
                    <label>账号（管理员用户名）</label>
                    <input type="text" name="admin_user" class="form-control" placeholder="例如：admin" required>
                </div>
                <div class="form-group">
                    <label>管理员姓名</label>
                    <input type="text" name="admin_name" class="form-control" placeholder="您的真实姓名" required>
                </div>
                <div class="form-group">
                    <label>登录密码</label>
                    <input type="password" name="admin_pass" class="form-control" placeholder="至少6位" required minlength="6">
                </div>
                <button type="submit" class="btn-install">完成安装</button>
            </form>
        <?php endif; ?>
        
        <?php if ($step != 3): ?>
            <div class="footer-link">已有系统？ <a href="login.php">去登录</a></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>