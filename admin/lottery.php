<?php
require_once '../includes/admin_header.php';
if (!$isTeacher) die('无权访问');

$message = '';
$error = '';

// 1.【自动清理】清理 config 表中重复的配置记录
try {
    $pdo->exec("DELETE t1 FROM config t1 INNER JOIN config t2 WHERE t1.id > t2.id AND t1.config_key = t2.config_key");
} catch (Exception $e) {}

// 2. 分别处理积分抽奖和惩罚抽奖的保存
if (isset($_POST['save_lottery'])) {
    $enabled = $_POST['lottery_enabled'] ?? '0';
    $cost = max(0, intval($_POST['lottery_cost'] ?? 10));

    $stmt = $pdo->prepare("SELECT id FROM config WHERE config_key = 'lottery_enabled'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE config SET config_value = ? WHERE config_key = 'lottery_enabled'")->execute([$enabled]);
    } else {
        $pdo->prepare("INSERT INTO config (config_key, config_value) VALUES ('lottery_enabled', ?)")->execute([$enabled]);
    }

    $stmt = $pdo->prepare("SELECT id FROM config WHERE config_key = 'lottery_cost'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE config SET config_value = ? WHERE config_key = 'lottery_cost'")->execute([$cost]);
    } else {
        $pdo->prepare("INSERT INTO config (config_key, config_value) VALUES ('lottery_cost', ?)")->execute([$cost]);
    }

    $message = '积分抽奖设置已保存';
}

if (isset($_POST['save_penalty'])) {
    $penEnabled = $_POST['penalty_enabled'] ?? '0';
    $penCount = max(1, intval($_POST['penalty_count'] ?? 3));

    $stmt = $pdo->prepare("SELECT id FROM config WHERE config_key = 'penalty_enabled'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE config SET config_value = ? WHERE config_key = 'penalty_enabled'")->execute([$penEnabled]);
    } else {
        $pdo->prepare("INSERT INTO config (config_key, config_value) VALUES ('penalty_enabled', ?)")->execute([$penEnabled]);
    }

    $stmt = $pdo->prepare("SELECT id FROM config WHERE config_key = 'penalty_count'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE config SET config_value = ? WHERE config_key = 'penalty_count'")->execute([$penCount]);
    } else {
        $pdo->prepare("INSERT INTO config (config_key, config_value) VALUES ('penalty_count', ?)")->execute([$penCount]);
    }

    $message = '惩罚抽奖设置已保存';
}

// 3. 读取最新状态
$lottery_enabled = getConfig('lottery_enabled') ?: '0';
$lottery_cost = getConfig('lottery_cost') ?: '10';
$penalty_enabled = getConfig('penalty_enabled') ?: '0';
$penalty_count = getConfig('penalty_count') ?: '3';

// 添加/删除/编辑积分奖品
if (isset($_POST['add_prize'])) {
    $name = trim($_POST['prize_name'] ?? '');
    $prob = max(1, intval($_POST['probability'] ?? 10));
    $total = max(1, intval($_POST['total'] ?? 100));
    if (empty($name)) $error = '奖品名称不能为空';
    else {
        $stmt = $pdo->prepare("INSERT INTO prizes (name, probability, total) VALUES (?, ?, ?)");
        $stmt->execute([$name, $prob, $total]);
        $message = '积分奖品添加成功';
    }
}

if (isset($_POST['edit_prize_submit'])) {
    $id = intval($_POST['edit_prize_id']);
    $name = trim($_POST['edit_prize_name']);
    $prob = max(1, intval($_POST['edit_prize_prob']));
    $total = max(1, intval($_POST['edit_prize_total']));
    if (empty($name)) {
        $error = '奖品名称不能为空';
    } else {
        $stmt = $pdo->prepare("UPDATE prizes SET name = ?, probability = ?, total = ? WHERE id = ?");
        $stmt->execute([$name, $prob, $total, $id]);
        $message = '积分奖品已更新';
    }
}

if (isset($_GET['delete_prize'])) {
    $id = (int)$_GET['delete_prize'];
    $pdo->prepare("DELETE FROM prizes WHERE id = ?")->execute([$id]);
    $message = '积分奖品已删除';
}

// 添加/删除/编辑惩罚奖品
if (isset($_POST['add_penalty_prize'])) {
    $name = trim($_POST['penalty_prize_name'] ?? '');
    $prob = max(1, intval($_POST['penalty_probability'] ?? 10));
    $total = max(1, intval($_POST['penalty_total'] ?? 100));
    if (empty($name)) $error = '惩罚奖品名称不能为空';
    else {
        $stmt = $pdo->prepare("INSERT INTO penalty_prizes (name, probability, total) VALUES (?, ?, ?)");
        $stmt->execute([$name, $prob, $total]);
        $message = '惩罚奖品添加成功';
    }
}

if (isset($_POST['edit_penalty_prize_submit'])) {
    $id = intval($_POST['edit_penalty_prize_id']);
    // 注意：前端提交的字段名与积分编辑一致，都是 edit_prize_name / edit_prize_prob / edit_prize_total
    $name = trim($_POST['edit_prize_name']);
    $prob = max(1, intval($_POST['edit_prize_prob']));
    $total = max(1, intval($_POST['edit_prize_total']));
    if (empty($name)) {
        $error = '惩罚奖品名称不能为空';
    } else {
        $stmt = $pdo->prepare("UPDATE penalty_prizes SET name = ?, probability = ?, total = ? WHERE id = ?");
        $stmt->execute([$name, $prob, $total, $id]);
        $message = '惩罚奖品已更新';
    }
}

if (isset($_GET['delete_penalty_prize'])) {
    $id = (int)$_GET['delete_penalty_prize'];
    $pdo->prepare("DELETE FROM penalty_prizes WHERE id = ?")->execute([$id]);
    $message = '惩罚奖品已删除';
}

// 奖品列表
$prizes = $pdo->query("SELECT * FROM prizes ORDER BY id ASC")->fetchAll();
$penaltyPrizes = $pdo->query("SELECT * FROM penalty_prizes ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeBuddy - 抽奖设置</title>
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

        /* --- 消息框 --- */
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
        .msg-error { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

        /* --- 卡片 --- */
        .card-box {
            background: white;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            /* ---- 新增入场动画（两个卡片分别延迟） ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
        }
        .card-box:nth-of-type(1) { animation-delay: 0.15s; }
        .card-box:nth-of-type(2) { animation-delay: 0.25s; }

        .card-box h5 { font-size: 15px; margin-bottom: 15px; color: #374151; }
        .card-box hr { border: 0; border-top: 1px solid #f3f4f6; margin: 20px 0; }

        /* --- 表单 --- */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            align-items: flex-end;
        }
        .form-group {
            flex: 1;
            min-width: 150px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #4b5563;
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
        .btn-danger-outline {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            border: 1px solid #ef4444;
            color: #ef4444;
            background: transparent;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-danger-outline:hover { background: #fef2f2; border-color: #dc2626; color: #dc2626; }
        .btn-edit-outline {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            border: 1px solid #4f46e5;
            color: #4f46e5;
            background: transparent;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
            margin-right: 6px;
        }
        .btn-edit-outline:hover { background: #eef2ff; border-color: #4338ca; color: #4338ca; }

        /* --- 表格（横向滚动） --- */
        .table-wrapper {
            background: transparent;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
        }
        th { background: #f9fafb; color: #666; font-weight: normal; font-size: 13px; }
        td .actions { display: flex; gap: 4px; flex-wrap: wrap; }

        /* ---- 表格行入场动画（逐行出现） ---- */
        .card-box table tbody tr {
            opacity: 0;
            animation: fadeUp 0.4s ease forwards;
        }
        .card-box table tbody tr:nth-child(1) { animation-delay: 0.05s; }
        .card-box table tbody tr:nth-child(2) { animation-delay: 0.11s; }
        .card-box table tbody tr:nth-child(3) { animation-delay: 0.17s; }
        .card-box table tbody tr:nth-child(4) { animation-delay: 0.23s; }
        .card-box table tbody tr:nth-child(5) { animation-delay: 0.29s; }
        .card-box table tbody tr:nth-child(6) { animation-delay: 0.35s; }
        .card-box table tbody tr:nth-child(7) { animation-delay: 0.41s; }
        .card-box table tbody tr:nth-child(8) { animation-delay: 0.47s; }
        .card-box table tbody tr:nth-child(9) { animation-delay: 0.53s; }
        .card-box table tbody tr:nth-child(10) { animation-delay: 0.59s; }

        /* --- 添加奖品行（各自延迟） --- */
        .add-prize-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-top: 15px;
            background: #f9fafb;
            padding: 12px 15px;
            border-radius: 8px;
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
        }
        .card-box:nth-of-type(1) .add-prize-row { animation-delay: 0.2s; }
        .card-box:nth-of-type(2) .add-prize-row { animation-delay: 0.3s; }

        .add-prize-row .input-group {
            flex: 1;
            min-width: 100px;
        }
        .add-prize-row .input-group input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
        }
        .add-prize-row .input-group input:focus { border-color: #4f46e5; }

        /* --- 模态框 --- */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal-box {
            background: white;
            border-radius: 12px;
            padding: 25px 30px 30px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            animation: fadeIn 0.2s ease-out;
        }
        @keyframes fadeIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-box h3 { font-size: 18px; margin-bottom: 15px; }
        .modal-box .form-group { margin-bottom: 12px; }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        .modal-footer button {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
        }
        .modal-footer .btn-cancel { background: #f3f4f6; color: #4b5563; }
        .modal-footer .btn-cancel:hover { background: #e5e7eb; }
        .modal-footer .btn-primary { background: #4f46e5; color: white; }
        .modal-footer .btn-primary:hover { background: #4338ca; }
        .modal-footer .btn-danger { background: #dc2626; color: white; }
        .modal-footer .btn-danger:hover { background: #b91c1c; }

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
        .btn-primary,
        .btn-danger-outline,
        .btn-edit-outline,
        .modal-footer button {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s, color 0.2s, border-color 0.2s;
        }
        .top-navbar .nav-links a:hover,
        .top-navbar .btn-logout:hover,
        .btn-primary:hover,
        .btn-danger-outline:hover,
        .btn-edit-outline:hover,
        .modal-footer button:hover {
            transform: scale(1.03);
        }
        .btn-primary:active,
        .btn-danger-outline:active,
        .btn-edit-outline:active {
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
            .card-box { padding: 16px 18px; }
            .form-group { min-width: 140px; }
            .add-prize-row .input-group { min-width: 80px; }
        }

        @media (max-width: 768px) {
            .top-navbar { min-height: 56px; padding: 0 12px; }
            .top-navbar .logo-area img { height: 28px; }
            .top-navbar .user-area { font-size: 13px; gap: 8px; }
            .top-navbar .user-avatar { width: 28px; height: 28px; font-size: 12px; }
            .top-navbar .btn-logout { font-size: 13px; }
            .main-content { padding: 15px; }
            .page-title h1 { font-size: 20px; }
            .form-row { flex-direction: column; gap: 12px; }
            .form-group { min-width: auto; }
            .form-group[style*="flex: 0 0 auto;"] { flex: 1 1 auto !important; width: 100%; }
            .add-prize-row { flex-direction: column; align-items: stretch; }
            .add-prize-row .input-group { min-width: auto; }
            .add-prize-row .btn-primary { width: 100%; }
            table { font-size: 13px; min-width: 360px; }
            th, td { padding: 10px 8px; }
            td .actions { flex-direction: column; gap: 4px; }
            .modal-box { width: 95%; padding: 20px; }
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
            .form-control, .custom-select { font-size: 13px; padding: 6px 10px; }
            .btn-primary { padding: 6px 14px; font-size: 13px; width: 100%; }
            .add-prize-row { padding: 10px 12px; }
            .add-prize-row .input-group input { font-size: 13px; padding: 5px 8px; }
            table { font-size: 12px; min-width: 320px; }
            th, td { padding: 6px 4px; }
            .modal-box { padding: 15px; }
            .modal-box h3 { font-size: 16px; }
            .modal-footer button { font-size: 13px; padding: 6px 14px; }
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
            <a href="/admin/lottery.php" class="active">抽奖设置</a>
            <a href="/admin/settings.php">系统设置</a>
            <a href="/admin/logs.php">操作日志</a>
            <a href="/index.php">返回首页</a>
            <a href="/lottery.php">前往抽奖</a>
        </nav>
    </header>

    <!-- 主内容 -->
    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>🎁 抽奖设置</h1>
                <p>积分与惩罚抽奖开关、奖品及权重配置</p>
            </div>
        </div>

        <?php if ($message): ?><div class="msg-box msg-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-box msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- ===== 积分抽奖 ===== -->
        <div class="card-box">
            <h5>积分抽奖</h5>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>开关</label>
                        <select name="lottery_enabled" class="custom-select">
                            <option value="1" <?= $lottery_enabled=='1'?'selected':'' ?>>开启</option>
                            <option value="0" <?= $lottery_enabled=='0'?'selected':'' ?>>关闭</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>每次消耗积分</label>
                        <input type="number" name="lottery_cost" class="form-control" value="<?= $lottery_cost ?>" min="0">
                    </div>
                    <div class="form-group" style="flex: 0 0 auto; min-width: auto;">
                        <button type="submit" name="save_lottery" class="btn-primary">保存</button>
                    </div>
                </div>
            </form>

            <hr>
            <h6>积分奖品</h6>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>名称</th><th>权重</th><th>库存</th><th>已抽</th><th style="width:120px;">操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($prizes as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= $p['probability'] ?></td>
                            <td><?= $p['total'] ?></td>
                            <td><?= $p['drawn'] ?></td>
                            <td>
                                <div class="actions">
                                    <button class="btn-edit-outline" onclick="openEditModal('prize', <?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>', <?= $p['probability'] ?>, <?= $p['total'] ?>)">编辑</button>
                                    <a href="javascript:void(0)" class="btn-danger-outline" onclick="openDeleteModal('prize', <?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')">删除</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" class="add-prize-row">
                <div class="input-group"><input type="text" name="prize_name" placeholder="奖品名称" required></div>
                <div class="input-group" style="flex:0.5; min-width:80px;"><input type="number" name="probability" value="10" min="1" placeholder="权重"></div>
                <div class="input-group" style="flex:0.5; min-width:80px;"><input type="number" name="total" value="100" min="1" placeholder="库存"></div>
                <button type="submit" name="add_prize" class="btn-primary">添加</button>
            </form>
        </div>

        <!-- ===== 惩罚抽奖 ===== -->
        <div class="card-box">
            <h5>惩罚抽奖</h5>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>开关</label>
                        <select name="penalty_enabled" class="custom-select">
                            <option value="1" <?= $penalty_enabled=='1'?'selected':'' ?>>开启</option>
                            <option value="0" <?= $penalty_enabled=='0'?'selected':'' ?>>关闭</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>最后几名人数</label>
                        <input type="number" name="penalty_count" class="form-control" value="<?= $penalty_count ?>" min="1">
                    </div>
                    <div class="form-group" style="flex: 0 0 auto; min-width: auto;">
                        <button type="submit" name="save_penalty" class="btn-primary">保存</button>
                    </div>
                </div>
            </form>
            <hr>
            <h6>惩罚奖品</h6>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>名称</th><th>权重</th><th>库存</th><th>已抽</th><th style="width:120px;">操作</th></tr></thead>
                    <tbody>
                    <?php foreach ($penaltyPrizes as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= $p['probability'] ?></td>
                            <td><?= $p['total'] ?></td>
                            <td><?= $p['drawn'] ?></td>
                            <td>
                                <div class="actions">
                                    <button class="btn-edit-outline" onclick="openEditModal('penalty', <?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>', <?= $p['probability'] ?>, <?= $p['total'] ?>)">编辑</button>
                                    <a href="javascript:void(0)" class="btn-danger-outline" onclick="openDeleteModal('penalty', <?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')">删除</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" class="add-prize-row">
                <div class="input-group"><input type="text" name="penalty_prize_name" placeholder="惩罚奖品名称" required></div>
                <div class="input-group" style="flex:0.5; min-width:80px;"><input type="number" name="penalty_probability" value="10" min="1" placeholder="权重"></div>
                <div class="input-group" style="flex:0.5; min-width:80px;"><input type="number" name="penalty_total" value="100" min="1" placeholder="库存"></div>
                <button type="submit" name="add_penalty_prize" class="btn-primary">添加</button>
            </form>
        </div>
    </main>

    <!-- 删除确认模态框 -->
    <div id="deleteModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3>⚠️ 确认删除</h3>
            <div class="modal-body">
                <p style="margin-bottom:15px; color:#4b5563;">确定要永久删除此奖品吗？不可恢复。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="document.getElementById('deleteModal').style.display='none'">取消</button>
                <button type="button" class="btn-danger" onclick="confirmDelete()">确认删除</button>
            </div>
        </div>
    </div>

    <!-- 编辑奖品模态框 -->
    <div id="editModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3 id="editModalTitle">编辑奖品</h3>
            <form method="post" id="editForm">
                <!-- 隐藏字段：用于区分积分/惩罚，以及奖品ID -->
                <input type="hidden" name="edit_type" id="editType" value="prize">
                <input type="hidden" name="edit_prize_id" id="editPrizeId" value="0">
                <input type="hidden" name="edit_penalty_prize_id" id="editPenaltyPrizeId" value="0">

                <div class="form-group">
                    <label>名称</label>
                    <input type="text" name="edit_prize_name" id="editPrizeName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>权重</label>
                    <input type="number" name="edit_prize_prob" id="editPrizeProb" class="form-control" min="1" required>
                </div>
                <div class="form-group">
                    <label>库存</label>
                    <input type="number" name="edit_prize_total" id="editPrizeTotal" class="form-control" min="1" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').style.display='none'">取消</button>
                    <button type="submit" class="btn-primary" id="editSubmitBtn">保存修改</button>
                </div>
            </form>
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

        // 删除模态框逻辑
        let delType = null, delId = null;
        function openDeleteModal(type, id, name) {
            delType = type;
            delId = id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
        function confirmDelete() {
            if (delType === 'prize') location.href = '?delete_prize=' + delId;
            else if (delType === 'penalty') location.href = '?delete_penalty_prize=' + delId;
        }

        // 编辑模态框逻辑
        function openEditModal(type, id, name, prob, total) {
            const modal = document.getElementById('editModal');
            const title = document.getElementById('editModalTitle');
            const form = document.getElementById('editForm');
            const typeInput = document.getElementById('editType');
            const idInputPrize = document.getElementById('editPrizeId');
            const idInputPenalty = document.getElementById('editPenaltyPrizeId');
            const nameInput = document.getElementById('editPrizeName');
            const probInput = document.getElementById('editPrizeProb');
            const totalInput = document.getElementById('editPrizeTotal');
            const submitBtn = document.getElementById('editSubmitBtn');

            // 清空之前的隐藏 ID
            idInputPrize.value = 0;
            idInputPenalty.value = 0;

            if (type === 'prize') {
                title.textContent = '编辑积分奖品';
                typeInput.value = 'prize';
                idInputPrize.value = id;
                submitBtn.name = 'edit_prize_submit';
            } else if (type === 'penalty') {
                title.textContent = '编辑惩罚奖品';
                typeInput.value = 'penalty';
                idInputPenalty.value = id;
                submitBtn.name = 'edit_penalty_prize_submit';
            }

            nameInput.value = name;
            probInput.value = prob;
            totalInput.value = total;

            modal.style.display = 'flex';
        }

        // 点击模态框外部关闭
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });

        // 编辑表单提交前，根据类型确保对应的隐藏 ID 正确
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const type = document.getElementById('editType').value;
            if (type === 'prize') {
                document.getElementById('editPenaltyPrizeId').value = 0;
            } else {
                document.getElementById('editPrizeId').value = 0;
            }
        });
    </script>
</body>
</html>
<?php require_once '../includes/admin_footer.php'; ?>