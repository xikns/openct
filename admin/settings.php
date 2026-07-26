<?php
// ==========================================
// 1. PHP 后端数据逻辑 (完全保留，未改动)
// ==========================================
require_once '../includes/admin_header.php';
if (!$isTeacher) die('无权访问');

$message = '';
if (isset($_POST['save_settings'])) {
    $allow_register = $_POST['allow_register'] ?? '0';
    $website_title = trim($_POST['website_title'] ?? '班级积分管理系统');
    $stmt = $pdo->prepare("UPDATE config SET config_value = ? WHERE config_key = ?");
    $stmt->execute([$allow_register, 'allow_register']);
    $stmt->execute([$website_title, 'website_title']);
    $message = '设置已保存';
}

$allow_register = getConfig('allow_register');
$website_title = getConfig('website_title');
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeBuddy - 系统设置</title>
    <style>
        /* ==========================================
           CSS 样式重置与布局（统一后台风格）
           ========================================== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "PingFang SC", sans-serif;
        }
        body {
            background-color: #f5f6fa;
            color: #333;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        a { text-decoration: none; color: inherit; }

        /* --- 顶部导航栏（含汉堡） --- */
        .top-navbar {
            background: #ffffff;
            padding: 0 20px;
            min-height: 64px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            flex-shrink: 0;
            position: relative;
            z-index: 100;
            gap: 10px 0;
        }
        .top-navbar .logo-area {
            display: flex;
            align-items: center;
            margin-right: 30px;
        }
        .top-navbar .logo-area img {
            height: 32px;
            object-fit: contain;
        }
        .navbar-toggler {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 4px 8px;
            color: #333;
            margin-left: 10px;
            order: 2;
        }
        .top-navbar .nav-links {
            display: flex;
            gap: 20px;
            flex: 1;
            flex-wrap: wrap;
            align-items: center;
        }
        .top-navbar .nav-links a {
            font-size: 14px;
            color: #555;
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 4px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .top-navbar .nav-links a:hover { color: #4f46e5; }
        .top-navbar .nav-links a.active {
            color: #4f46e5;
            background: #eef2ff;
        }
        .top-navbar .user-area {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            flex-shrink: 0;
            margin-left: auto;
            order: 1;
        }
        .top-navbar .user-avatar {
            width: 32px; height: 32px;
            background: #4f46e5; color: white;
            border-radius: 50%;
            display: flex; justify-content: center; align-items: center;
            font-size: 14px;
        }
        .top-navbar .btn-logout {
            color: #ef4444;
            font-weight: 500;
            cursor: pointer;
        }

        /* --- 主内容区 --- */
        .main-content {
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.05s;
        }
        .page-title h1 { font-size: 22px; font-weight: bold; }
        .page-title p { font-size: 12px; color: #999; margin-top: 5px; }

        /* --- 消息提示框 --- */
        .msg-box {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid transparent;
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.1s;
        }
        .msg-success { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }

        /* --- 设置卡片 --- */
        .card-box {
            background: white;
            border-radius: 10px;
            padding: 25px 30px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.15s;
        }

        /* --- 表单元素 --- */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-control, .custom-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
            background: white;
        }
        .form-control:focus, .custom-select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }

        /* --- 按钮 --- */
        .btn-primary {
            padding: 8px 20px;
            border-radius: 6px;
            border: none;
            background: #4f46e5;
            color: white;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-primary:hover { background: #4338ca; }

        /* ==========================================
           新增：全局动画关键帧 & 交互增强
           ========================================== */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 导航菜单展开/收起过渡优化 */
        .top-navbar .nav-links {
            transition: max-height 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        opacity 0.4s ease,
                        visibility 0s linear 0.4s,
                        padding 0.3s ease;
        }
        .top-navbar .nav-links.open {
            transition: max-height 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        opacity 0.3s ease,
                        visibility 0s linear 0s,
                        padding 0.3s ease;
        }

        /* 可点击元素悬停微动效（放大） */
        .top-navbar .nav-links a,
        .top-navbar .btn-logout,
        .btn-primary {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s, color 0.2s;
        }
        .top-navbar .nav-links a:hover,
        .top-navbar .btn-logout:hover,
        .btn-primary:hover {
            transform: scale(1.03);
        }
        .btn-primary:active {
            transform: scale(0.97);
        }

        /* ==========================================
           响应式适配（统一断点）
           ========================================== */

        @media (max-width: 992px) {
            .top-navbar {
                min-height: 60px;
                padding: 0 15px;
            }
            .navbar-toggler {
                display: block;
            }
            .top-navbar .logo-area {
                order: 0;
                margin-right: auto;
            }
            .top-navbar .user-area {
                order: 1;
                margin-left: auto;
                gap: 10px;
            }
            .top-navbar .nav-links {
                flex: 0 0 100%;
                order: 3;
                flex-direction: column;
                background: #fff;
                padding: 0 10px;
                gap: 0;
                border-top: 1px solid #f0f0f0;
                margin-top: 8px;
                box-sizing: border-box;
                visibility: hidden;
                max-height: 0;
                opacity: 0;
                overflow: hidden;
                transition: max-height 0.35s ease, opacity 0.35s ease, visibility 0s linear 0.35s;
                width: 100%;
            }
            .top-navbar .nav-links.open {
                visibility: visible;
                max-height: 500px;
                opacity: 1;
                transition: max-height 0.35s ease, opacity 0.35s ease, visibility 0s linear 0s;
                padding: 10px 10px;
            }
            .top-navbar .nav-links a {
                white-space: normal;
                padding: 12px 20px;
                width: 100%;
                border-radius: 0;
                font-size: 15px;
                border-bottom: 1px solid #f5f5f5;
            }
            .top-navbar .nav-links a:last-child { border-bottom: none; }

            .top-navbar.menu-open {
                padding-left: 0;
                padding-right: 0;
            }
            .top-navbar.menu-open .logo-area,
            .top-navbar.menu-open .user-area {
                display: none;
            }

            .main-content { padding: 20px; }
            .card-box { padding: 20px; max-width: 100%; }
        }

        @media (max-width: 768px) {
            .top-navbar { min-height: 56px; padding: 0 12px; }
            .top-navbar .logo-area img { height: 28px; }
            .top-navbar .user-area { font-size: 13px; gap: 8px; }
            .top-navbar .user-avatar { width: 28px; height: 28px; font-size: 12px; }
            .top-navbar .btn-logout { font-size: 13px; }
            .main-content { padding: 15px; }
            .page-title h1 { font-size: 20px; }
            .card-box { padding: 16px; }
            .form-control, .custom-select { font-size: 13px; padding: 6px 10px; }
            .btn-primary { width: 100%; text-align: center; }
        }

        @media (max-width: 480px) {
            .top-navbar { min-height: 50px; padding: 0 8px; }
            .top-navbar .logo-area img { height: 24px; }
            .top-navbar .user-area { font-size: 12px; gap: 5px; }
            .top-navbar .user-avatar { width: 24px; height: 24px; font-size: 10px; }
            .top-navbar .btn-logout { font-size: 12px; }
            .navbar-toggler { font-size: 20px; padding: 2px 6px; margin-left: 8px; }
            .main-content { padding: 10px; }
            .page-title h1 { font-size: 18px; }
            .page-title p { font-size: 11px; }
            .card-box { padding: 12px 14px; }
            .form-group label { font-size: 13px; }
            .form-control, .custom-select { font-size: 13px; padding: 6px 10px; }
            .btn-primary { font-size: 13px; padding: 6px 14px; }
        }
    </style>
</head>
<body>

    <!-- 顶部导航栏（统一汉堡结构） -->
    <header class="top-navbar" id="topNavbar">
        <div class="logo-area">
            <img src="/static/picture/ailogo.png" alt="Logo">
        </div>
        
        <div class="user-area">
            <div class="user-avatar"><?= mb_substr($currentUser['realname'], 0, 1) ?></div>
            <span><?= htmlspecialchars($currentUser['realname']) ?></span>
            <a href="logout.php" class="btn-logout">退出</a>
        </div>

        <button class="navbar-toggler" id="navbarToggler" aria-label="切换导航">
            ☰
        </button>

        <nav class="nav-links" id="navLinks">
            <a href="/admin/index.php">概览</a>
            <a href="/admin/points.php">积分管理</a>
            <a href="/admin/users.php">用户管理</a>
            <a href="/admin/homepage.php">首页管理</a>
            <a href="/admin/lottery.php">抽奖设置</a>
            <a href="/admin/settings.php" class="active">系统设置</a>
            <a href="/admin/logs.php">操作日志</a>
            <a href="/index.php">返回首页</a>
        </nav>
    </header>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>系统设置</h1>
                <p>网站基础配置与注册控制</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="msg-box msg-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- 系统设置卡片 -->
        <div class="card-box">
            <form method="post">
                <div class="form-group">
                    <label>网站标题</label>
                    <input type="text" name="website_title" class="form-control" value="<?= htmlspecialchars($website_title) ?>">
                </div>
                <div class="form-group">
                    <label>开放注册</label>
                    <select name="allow_register" class="custom-select">
                        <option value="1" <?= $allow_register == '1' ? 'selected' : '' ?>>开启 - 学生可在登录页自行注册</option>
                        <option value="0" <?= $allow_register == '0' ? 'selected' : '' ?>>关闭 - 只有教师可添加用户</option>
                    </select>
                </div>
                <button type="submit" name="save_settings" class="btn-primary">保存设置</button>
            </form>
        </div>
    </main>

    <script>
        // 汉堡菜单切换（与后台其他页面一致）
        document.addEventListener('DOMContentLoaded', function() {
            const toggler = document.getElementById('navbarToggler');
            const navLinks = document.getElementById('navLinks');
            const topNavbar = document.getElementById('topNavbar');
            toggler.addEventListener('click', function() {
                navLinks.classList.toggle('open');
                topNavbar.classList.toggle('menu-open');
            });
            navLinks.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    navLinks.classList.remove('open');
                    topNavbar.classList.remove('menu-open');
                });
            });
        });
    </script>

</body>
</html>
<?php require_once '../includes/admin_footer.php'; ?>