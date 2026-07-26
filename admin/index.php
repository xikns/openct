<?php
// ==========================================
// 1. PHP 后端数据逻辑 (源自你的后台系统)
// ==========================================
require_once '../includes/admin_header.php';
// 仅老师可访问仪表盘
if (!$isTeacher) die('无权访问');

// --- 获取统计卡片数据 ---
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student','student_admin')")->fetchColumn();
$maxPoints  = $pdo->query("SELECT MAX(points) FROM users WHERE role IN ('student','student_admin')")->fetchColumn();
$avgPoints  = $pdo->query("SELECT AVG(points) FROM users WHERE role IN ('student','student_admin')")->fetchColumn();

// --- 前十名（按积分降序）---
$top10 = $pdo->query("
    SELECT realname, points, avatar 
    FROM users 
    WHERE role IN ('student','student_admin') 
    ORDER BY points DESC 
    LIMIT 10
")->fetchAll();

// --- 倒数五名（按积分升序）---
$bottom5 = $pdo->query("
    SELECT realname, points, avatar 
    FROM users 
    WHERE role IN ('student','student_admin') 
    ORDER BY points ASC 
    LIMIT 5
")->fetchAll();

// 计算进度条的最大值
$maxRankPoints = isset($top10[0]) ? $top10[0]['points'] : 1;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeBuddy - 积分概况</title>
    <style>
        /* ==========================================
           CSS 样式重置与布局
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

        /* --- 顶部导航栏 --- */
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
            order: 0; /* 始终在最左 */
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
        }
        .top-navbar .nav-links {
            display: flex;
            gap: 20px;
            flex: 1;               /* 占据剩余空间，居中 */
            flex-wrap: wrap;
            align-items: center;
            order: 1;              /* 中间位置 */
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
            margin-left: auto;     /* 电脑端靠右 */
            order: 2;              /* 最右边 */
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
            margin-bottom: 20px;
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.05s;
        }
        .page-title h1 { font-size: 22px; font-weight: bold; }
        .page-title p { font-size: 12px; color: #999; margin-top: 5px; }

        /* --- 统计卡片 --- */
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: white;
            padding: 25px 20px;
            border-radius: 10px;
            flex: 1;
            min-width: 120px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.5s ease forwards;
        }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }

        .stat-card .number { font-size: 28px; font-weight: bold; margin-bottom: 5px; display: block; }
        .stat-card .label { font-size: 13px; color: #888; }

        /* --- 内容块（双栏布局） --- */
        .content-row {
            display: flex;
            gap: 20px;
            align-items: stretch;
            flex-wrap: wrap;
        }
        .content-col-lg {
            flex: 2;
            min-width: 300px;
        }
        .content-col-sm {
            flex: 1;
            min-width: 250px;
        }
        .card-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            height: 100%;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.4s;
        }
        .card-box h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
            font-weight: 600;
        }

        /* --- 表格 --- */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 420px;
        }
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
            white-space: nowrap;
        }
        th { color: #666; font-weight: normal; font-size: 13px; }
        .rank-number {
            font-size: 20px;
            font-weight: bold;
            width: 40px;
            color: #333;
        }
        .rank-1 { color: #f59e0b; }
        .rank-2 { color: #9ca3af; }
        .rank-3 { color: #d97706; }
        .user-avatar-sm {
            width: 32px; height: 32px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            vertical-align: middle;
        }
        .progress-container {
            width: 120px;
            height: 6px;
            background: #e8e8ed;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: #4f46e5;
            border-radius: 4px;
        }
        .list-group-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            /* ---- 新增：初始透明，动画由下方规则控制 ---- */
            opacity: 0;
            animation: fadeUp 0.4s ease forwards;
        }
        .list-group-item:last-child { border-bottom: none; }
        .badge-points {
            margin-left: auto;
            background: #eef2ff;
            color: #4f46e5;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        /* ---- 新增：表格行逐行动画 ---- */
        .content-col-lg .card-box table tbody tr {
            opacity: 0;
            animation: fadeUp 0.4s ease forwards;
        }
        /* 延迟从 0.1s 开始，每行递增 0.08s，覆盖10行 */
        .content-col-lg .card-box table tbody tr:nth-child(1) { animation-delay: 0.1s; }
        .content-col-lg .card-box table tbody tr:nth-child(2) { animation-delay: 0.18s; }
        .content-col-lg .card-box table tbody tr:nth-child(3) { animation-delay: 0.26s; }
        .content-col-lg .card-box table tbody tr:nth-child(4) { animation-delay: 0.34s; }
        .content-col-lg .card-box table tbody tr:nth-child(5) { animation-delay: 0.42s; }
        .content-col-lg .card-box table tbody tr:nth-child(6) { animation-delay: 0.50s; }
        .content-col-lg .card-box table tbody tr:nth-child(7) { animation-delay: 0.58s; }
        .content-col-lg .card-box table tbody tr:nth-child(8) { animation-delay: 0.66s; }
        .content-col-lg .card-box table tbody tr:nth-child(9) { animation-delay: 0.74s; }
        .content-col-lg .card-box table tbody tr:nth-child(10) { animation-delay: 0.82s; }

        /* ---- 新增：列表项逐项动画 ---- */
        .list-group-item:nth-child(1) { animation-delay: 0.1s; }
        .list-group-item:nth-child(2) { animation-delay: 0.18s; }
        .list-group-item:nth-child(3) { animation-delay: 0.26s; }
        .list-group-item:nth-child(4) { animation-delay: 0.34s; }
        .list-group-item:nth-child(5) { animation-delay: 0.42s; }

        /* --- 动画关键帧 --- */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ---- 增强导航菜单展开/收起过渡 ---- */
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

        /* ---- 悬停微动效（给可点击元素） ---- */
        .btn-primary, .btn-secondary, .btn-danger-outline,
        .top-navbar .nav-links a,
        .top-navbar .btn-logout {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s, color 0.2s;
        }
        .top-navbar .nav-links a:hover,
        .top-navbar .btn-logout:hover {
            transform: scale(1.03);
        }

        /* ==========================================
           响应式适配
           ========================================== */

        /* 平板及以下 (≤ 992px) */
        @media (max-width: 992px) {
            .top-navbar {
                min-height: 60px;
                padding: 0 15px;
            }
            .navbar-toggler {
                display: block;
                order: 2;                /* 放在 user-area 后面 */
                margin-left: 10px;
            }
            .top-navbar .logo-area {
                order: 0;
                margin-right: auto;
            }
            .top-navbar .user-area {
                order: 1;
                margin-left: auto;       /* 移动端靠右 */
                gap: 10px;
            }
            /* 移动端 nav-links 独占一行，折叠状态 */
            .top-navbar .nav-links {
                flex: 0 0 100%;          /* 强制换行 */
                order: 3;
                width: 100%;
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
            .top-navbar .nav-links a:last-child {
                border-bottom: none;
            }

            .top-navbar.menu-open {
                padding-left: 0;
                padding-right: 0;
            }
            .top-navbar.menu-open .logo-area,
            .top-navbar.menu-open .user-area {
                display: none;
            }

            /* 双栏变单栏 */
            .content-row {
                flex-direction: column;
            }
            .content-col-lg, .content-col-sm {
                flex: 1 1 auto;
                width: 100%;
                min-width: 0;
            }
            .main-content {
                padding: 20px;
            }
        }

        /* 手机 (≤ 768px) */
        @media (max-width: 768px) {
            .top-navbar {
                min-height: 56px;
                padding: 0 12px;
            }
            .top-navbar .logo-area img {
                height: 28px;
            }
            .top-navbar .user-area {
                font-size: 13px;
                gap: 8px;
            }
            .top-navbar .user-avatar {
                width: 28px; height: 28px;
                font-size: 12px;
            }
            .top-navbar .btn-logout {
                font-size: 13px;
            }
            .main-content {
                padding: 15px;
            }
            .page-title h1 { font-size: 20px; }
            .stat-card { padding: 18px 12px; }
            .stat-card .number { font-size: 24px; }
            .card-box { padding: 15px; }
            table { font-size: 13px; min-width: 350px; }
            th, td { padding: 8px 6px; }
            .rank-number { font-size: 17px; width: 30px; }
            .user-avatar-sm { width: 28px; height: 28px; margin-right: 6px; }
            .progress-container { width: 80px; }
            .badge-points { font-size: 11px; padding: 2px 10px; }
            .top-navbar .nav-links.open {
                max-height: 450px;
            }
        }

        /* 极小屏 (≤ 480px) */
        @media (max-width: 480px) {
            .top-navbar {
                min-height: 50px;
                padding: 0 8px;
            }
            .top-navbar .logo-area img {
                height: 24px;
            }
            .top-navbar .user-area {
                font-size: 12px;
                gap: 5px;
            }
            .top-navbar .user-avatar {
                width: 24px; height: 24px;
                font-size: 10px;
            }
            .top-navbar .btn-logout {
                font-size: 12px;
            }
            .navbar-toggler {
                font-size: 20px;
                padding: 2px 6px;
                margin-left: 8px;
            }
            .main-content {
                padding: 10px;
            }
            .page-title h1 { font-size: 18px; }
            .stats-row { gap: 8px; }
            .stat-card { padding: 12px 8px; min-width: 80px; }
            .stat-card .number { font-size: 20px; }
            .stat-card .label { font-size: 11px; }
            .card-box h3 { font-size: 14px; }
            table { font-size: 12px; min-width: 280px; }
            th, td { padding: 6px 4px; }
            .rank-number { font-size: 15px; width: 24px; }
            .user-avatar-sm { width: 22px; height: 22px; margin-right: 4px; }
            .progress-container { width: 60px; height: 5px; }
            .badge-points { font-size: 10px; padding: 1px 8px; }
            .list-group-item { padding: 6px 0; }
            .top-navbar .nav-links.open {
                max-height: 400px;
            }
        }
    </style>
</head>
<body>

    <!-- 顶部导航栏（调整 DOM 顺序：logo → nav-links → user-area → 汉堡） -->
    <header class="top-navbar" id="topNavbar">
        <!-- Logo 始终在最左 -->
        <div class="logo-area">
            <img src="/static/picture/ailogo.png" alt="Logo">
        </div>
        
        <!-- 导航链接（中间） -->
        <nav class="nav-links" id="navLinks">
            <a href="/admin/index.php" class="active">概览</a>
            <a href="/admin/points.php">积分管理</a>
            <a href="/admin/users.php">用户管理</a>
            <a href="/admin/homepage.php">首页管理</a>
            <a href="/admin/lottery.php">抽奖设置</a>
            <a href="/admin/settings.php">系统设置</a>
            <a href="/admin/logs.php">操作日志</a>
            <a href="/index.php">返回首页</a>
        </nav>

        <!-- 用户区（最右，靠 margin-left: auto 实现） -->
        <div class="user-area">
            <div class="user-avatar"><?= mb_substr($currentUser['realname'], 0, 1) ?></div>
            <span><?= htmlspecialchars($currentUser['realname']) ?></span>
            <a href="logout.php" class="btn-logout">退出</a>
        </div>

        <!-- 汉堡按钮（移动端显示，放在最后，但用 order 调整位置） -->
        <button class="navbar-toggler" id="navbarToggler" aria-label="切换导航">
            ☰
        </button>
    </header>

    <!-- 主内容区 -->
    <div class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>积分概况</h1>
                <p>班级积分管理与数据洞察</p>
            </div>
        </div>

        <!-- 统计卡片 -->
        <div class="stats-row">
            <div class="stat-card">
                <span class="number" style="color:#4f46e5;"><?= $totalUsers ?></span>
                <div class="label">总学生</div>
            </div>
            <div class="stat-card">
                <span class="number" style="color:#22c55e;"><?= $maxPoints ?></span>
                <div class="label">最高积分</div>
            </div>
            <div class="stat-card">
                <span class="number" style="color:#d97706;"><?= round($avgPoints, 1) ?></span>
                <div class="label">平均积分</div>
            </div>
        </div>

        <!-- 双栏 -->
        <div class="content-row">
            <div class="content-col-lg">
                <div class="card-box">
                    <h3>🏆 前十名</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:50px;">#</th>
                                    <th>学生</th>
                                    <th style="width:160px;">积分进度</th>
                                    <th style="width:60px; text-align:center;">积分</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top10 as $i => $s): ?>
                                <tr>
                                    <td class="rank-number <?= ($i+1 <= 3) ? 'rank-'.($i+1) : '' ?>">
                                        <?= $i + 1 ?>
                                    </td>
                                    <td>
                                        <img src="<?= $s['avatar'] ? '../uploads/avatars/'.$s['avatar'] : '../assets/default-avatar.png' ?>" 
                                             class="user-avatar-sm" alt="头像">
                                        <span style="font-weight:500;"><?= htmlspecialchars($s['realname']) ?></span>
                                    </td>
                                    <td>
                                        <div class="progress-container">
                                            <div class="progress-bar" style="width:<?= ($s['points'] / $maxRankPoints) * 100 ?>%;"></div>
                                        </div>
                                    </td>
                                    <td style="text-align:center; font-weight:600;"><?= $s['points'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="content-col-sm">
                <div class="card-box">
                    <h3>⚠️ 倒数五名</h3>
                    <div>
                        <?php foreach ($bottom5 as $s): ?>
                            <div class="list-group-item">
                                <img src="<?= $s['avatar'] ? '../uploads/avatars/'.$s['avatar'] : '../assets/default-avatar.png' ?>" 
                                     class="user-avatar-sm" alt="头像">
                                <span><?= htmlspecialchars($s['realname']) ?></span>
                                <span class="badge-points"><?= $s['points'] ?> 分</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 汉堡菜单切换
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