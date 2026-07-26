<?php
// ==========================================
// 1. PHP 后端数据逻辑 (完全保留，未改动)
// ==========================================
require_once '../includes/admin_header.php';
if (!$isTeacher) die('无权访问');

// 分页及搜索
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if (!empty($search)) {
    $where = " WHERE u.realname LIKE ? OR op.realname LIKE ?";
    $params = ["%{$search}%", "%{$search}%"];
}

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 总数
$countSql = "SELECT COUNT(*) FROM points_log pl 
             LEFT JOIN users u ON pl.user_id = u.id 
             LEFT JOIN users op ON pl.operator_id = op.id" . $where;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// 日志列表
$sql = "SELECT pl.*, u.realname as student_name, op.realname as operator_name 
        FROM points_log pl 
        LEFT JOIN users u ON pl.user_id = u.id 
        LEFT JOIN users op ON pl.operator_id = op.id 
        {$where} 
        ORDER BY pl.created_at DESC 
        LIMIT {$limit} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeBuddy - 操作日志</title>
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

        /* --- 工具栏 (搜索框) --- */
        .toolbar-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.12s;
        }
        .search-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .input-search {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            outline: none;
            font-size: 14px;
            width: 200px;
        }
        .input-search:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .btn-search {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 0 16px;
            border-radius: 6px;
            cursor: pointer;
            color: #555;
            transition: 0.2s;
        }
        .btn-search:hover { background: #e5e7eb; }

        /* --- 表格（横向滚动） --- */
        .table-wrapper {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.2s;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 600px;  /* 窄屏横向滚动 */
        }
        th, td {
            padding: 16px 15px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        th { background: #f9fafb; color: #666; font-weight: normal; font-size: 13px; }
        tr:last-child td { border-bottom: none; }
        
        .text-up { color: #22c55e; font-weight: 600; }
        .text-down { color: #ef4444; font-weight: 600; }
        .text-muted { color: #9ca3af; }
        .py-4 { padding-top: 20px; padding-bottom: 20px; }
        .text-center { text-align: center; }

        /* --- 分页 --- */
        .pagination-custom {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
            list-style: none;
            flex-wrap: wrap;
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.3s;
        }
        .pagination-custom li a {
            display: block;
            padding: 8px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            color: #555;
            font-size: 14px;
            transition: 0.2s;
        }
        .pagination-custom li a:hover { background: #f3f4f6; }
        .pagination-custom li.active a {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }

        /* ==========================================
           新增：动画关键帧 & 增强交互
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

        /* 可点击元素悬停微动效 */
        .top-navbar .nav-links a,
        .top-navbar .btn-logout,
        .btn-search,
        .pagination-custom li a {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s, color 0.2s;
        }
        .top-navbar .nav-links a:hover,
        .top-navbar .btn-logout:hover,
        .btn-search:hover,
        .pagination-custom li a:hover {
            transform: scale(1.03);
        }
        .pagination-custom li.active a:hover {
            transform: scale(1.03);
        }

        /* ==========================================
           响应式适配（统一断点）
           ========================================== */

        /* 平板及以下 (≤ 992px) */
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
            .toolbar-row { justify-content: center; }
            .input-search { width: 160px; }
        }

        /* 手机 (≤ 768px) */
        @media (max-width: 768px) {
            .top-navbar { min-height: 56px; padding: 0 12px; }
            .top-navbar .logo-area img { height: 28px; }
            .top-navbar .user-area { font-size: 13px; gap: 8px; }
            .top-navbar .user-avatar { width: 28px; height: 28px; font-size: 12px; }
            .top-navbar .btn-logout { font-size: 13px; }
            .main-content { padding: 15px; }
            .page-title h1 { font-size: 20px; }
            table { font-size: 13px; min-width: 480px; }
            th, td { padding: 10px 8px; }
            .input-search { width: 140px; }
            .pagination-custom li a { padding: 6px 12px; font-size: 13px; }
        }

        /* 极小屏 (≤ 480px) */
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
            table { font-size: 12px; min-width: 360px; }
            th, td { padding: 6px 4px; }
            .input-search { width: 120px; font-size: 13px; padding: 6px 10px; }
            .btn-search { padding: 0 12px; font-size: 13px; }
            .pagination-custom { gap: 5px; }
            .pagination-custom li a { padding: 4px 10px; font-size: 12px; }
        }
    </style>
</head>
<body>

    <!-- 顶部导航栏（与 users.php 完全一致） -->
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
            <a href="/admin/settings.php">系统设置</a>
            <a href="/admin/logs.php" class="active">操作日志</a>
            <a href="/index.php">返回首页</a>
        </nav>
    </header>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>操作日志</h1>
                <p>学生积分变动历史与追溯</p>
            </div>
        </div>

        <!-- 工具栏 (搜索框) -->
        <div class="toolbar-row">
            <form method="get" class="search-form">
                <input type="text" name="search" class="input-search" placeholder="搜索学生或操作人" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-search">🔍</button>
            </form>
        </div>

        <!-- 操作日志表格 -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>学生</th>
                        <th>变动分值</th>
                        <th>原因</th>
                        <th>操作人</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($logs): ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="fw-medium"><?= htmlspecialchars($log['student_name'] ?? '已删除用户') ?></td>
                        <td>
                            <span class="<?= $log['changed_points'] >= 0 ? 'text-up' : 'text-down' ?>">
                                <?= $log['changed_points'] > 0 ? '+' . $log['changed_points'] : $log['changed_points'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['reason'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($log['operator_name'] ?? '系统') ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">暂无操作记录</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
        <ul class="pagination-custom">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="<?= $i == $page ? 'active' : '' ?>">
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
        <?php endif; ?>
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