<?php
// ==========================================
// 1. PHP 后端数据逻辑 (完全保留，未改动)
// ==========================================
require_once '../includes/admin_header.php';
if (!$isTeacher && !$isStudentAdmin) die('无权访问');

// 处理加减分
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = (int)$_POST['user_id'];
    $change = (int)$_POST['change_points'];
    $reason = trim($_POST['reason'] ?? '');

    if ($action === 'add' || $action === 'subtract') {
        if ($action === 'subtract') $change = -abs($change);
        if ($change != 0) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ? AND role IN ('student','student_admin')");
            $stmt->execute([$change, $user_id]);
            $stmt2 = $pdo->prepare("INSERT INTO points_log (user_id, changed_points, reason, operator_id) VALUES (?, ?, ?, ?)");
            $stmt2->execute([$user_id, $change, $reason, $currentUser['id']]);
            $pdo->commit();
        }
    }

    // 批量导入CSV（仅教师）
    if ($action === 'upload_excel' && $isTeacher) {
        if ($_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            $handle = fopen($_FILES['excel_file']['tmp_name'], 'r');
            $pdo->beginTransaction();
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 2) {
                    $identifier = trim($data[0]);
                    $pointChange = (int)$data[1];
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR realname = ?) AND role IN ('student','student_admin')");
                    $stmt->execute([$identifier, $identifier]);
                    $u = $stmt->fetch();
                    if ($u) {
                        $stmt2 = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                        $stmt2->execute([$pointChange, $u['id']]);
                        $stmt3 = $pdo->prepare("INSERT INTO points_log (user_id, changed_points, reason, operator_id) VALUES (?, ?, '批量导入', ?)");
                        $stmt3->execute([$u['id'], $pointChange, $currentUser['id']]);
                    }
                }
            }
            fclose($handle);
            $pdo->commit();
        }
    }
    header('Location: points.php');
    exit;
}

// 获取学生列表
$students = $pdo->query("SELECT id, realname, username, points, avatar FROM users WHERE role IN ('student','student_admin') ORDER BY realname")->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeBuddy - 积分管理</title>
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

        /* --- 卡片 --- */
        .card-box {
            background: white;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.1s;
        }

        /* --- 批量导入行 --- */
        .form-csv-row {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .form-csv-row input[type="file"] {
            padding: 6px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            font-size: 14px;
            background: white;
        }
        .form-csv-row .btn-upload {
            background: #eef2ff;
            color: #4f46e5;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        .form-csv-row .btn-upload:hover { background: #dbeafe; }
        .text-muted { font-size: 12px; color: #9ca3af; display: block; margin-top: 5px; }

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
            animation-delay: 0.15s;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 500px;
        }
        th, td {
            padding: 16px 15px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        th { background: #f9fafb; color: #666; font-weight: normal; font-size: 13px; }
        tr:last-child td { border-bottom: none; }

        /* ---- 表格行逐行动画 ---- */
        .table-wrapper tbody tr {
            opacity: 0;
            animation: fadeUp 0.4s ease forwards;
        }
        .table-wrapper tbody tr:nth-child(1) { animation-delay: 0.1s; }
        .table-wrapper tbody tr:nth-child(2) { animation-delay: 0.15s; }
        .table-wrapper tbody tr:nth-child(3) { animation-delay: 0.20s; }
        .table-wrapper tbody tr:nth-child(4) { animation-delay: 0.25s; }
        .table-wrapper tbody tr:nth-child(5) { animation-delay: 0.30s; }
        .table-wrapper tbody tr:nth-child(6) { animation-delay: 0.35s; }
        .table-wrapper tbody tr:nth-child(7) { animation-delay: 0.40s; }
        .table-wrapper tbody tr:nth-child(8) { animation-delay: 0.45s; }
        .table-wrapper tbody tr:nth-child(9) { animation-delay: 0.50s; }
        .table-wrapper tbody tr:nth-child(10) { animation-delay: 0.55s; }

        .user-avatar-sm {
            width: 36px; height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        .badge-point {
            display: inline-block;
            background: #eef2ff;
            color: #4f46e5;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        /* --- 操作按钮 --- */
        .action-btns {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-add { background: #eef2ff; color: #4f46e5; }
        .btn-add:hover { background: #dbeafe; }
        .btn-sub { background: #fef2f2; color: #dc2626; }
        .btn-sub:hover { background: #fee2e2; }

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
            width: 420px;
            max-width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            animation: fadeIn 0.2s ease-out;
        }
        @keyframes fadeIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-box h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #1f2937;
        }
        .modal-body label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #4b5563;
            margin-top: 12px;
            margin-bottom: 4px;
        }
        .modal-body input[type="number"] {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }
        .modal-body input[type="number"]:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        .reason-quick-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 6px 0 10px;
        }
        .reason-quick-btns button {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: 0.2s;
            color: #4b5563;
        }
        .reason-quick-btns button:hover {
            background: #eef2ff;
            border-color: #4f46e5;
            color: #4f46e5;
        }
        .modal-body textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            resize: vertical;
            outline: none;
            font-family: inherit;
            transition: 0.2s;
        }
        .modal-body textarea:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
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
        .modal-footer .btn-confirm { background: #4f46e5; color: white; }
        .modal-footer .btn-confirm:hover { background: #4338ca; }

        .confirm-data-list {
            background: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #e5e7eb;
        }
        .confirm-data-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
            border-bottom: 1px dashed #e5e7eb;
        }
        .confirm-data-item:last-child { border-bottom: none; }
        .confirm-data-item .label { color: #6b7280; }
        .confirm-data-item .value { font-weight: 500; color: #1f2937; }

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
        .btn-action,
        .btn-upload,
        .modal-footer button,
        .reason-quick-btns button {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s, color 0.2s, border-color 0.2s;
        }
        .top-navbar .nav-links a:hover,
        .top-navbar .btn-logout:hover,
        .btn-action:hover,
        .btn-upload:hover,
        .modal-footer button:hover,
        .reason-quick-btns button:hover {
            transform: scale(1.03);
        }
        .btn-action:active,
        .btn-upload:active,
        .modal-footer button:active {
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
            .form-csv-row { flex-direction: column; align-items: stretch; }
            .form-csv-row input[type="file"] { width: 100%; }
            .form-csv-row .btn-upload { width: 100%; text-align: center; }
        }

        @media (max-width: 768px) {
            .top-navbar { min-height: 56px; padding: 0 12px; }
            .top-navbar .logo-area img { height: 28px; }
            .top-navbar .user-area { font-size: 13px; gap: 8px; }
            .top-navbar .user-avatar { width: 28px; height: 28px; font-size: 12px; }
            .top-navbar .btn-logout { font-size: 13px; }
            .main-content { padding: 15px; }
            .page-title h1 { font-size: 20px; }
            table { font-size: 13px; min-width: 420px; }
            th, td { padding: 10px 8px; }
            .user-avatar-sm { width: 28px; height: 28px; }
            .badge-point { font-size: 13px; padding: 2px 10px; }
            .btn-action { font-size: 12px; padding: 5px 10px; }
            .action-btns { gap: 6px; }
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
            table { font-size: 12px; min-width: 340px; }
            th, td { padding: 6px 4px; }
            .user-avatar-sm { width: 22px; height: 22px; }
            .badge-point { font-size: 12px; padding: 2px 8px; }
            .btn-action { font-size: 11px; padding: 4px 8px; }
            .action-btns { gap: 4px; }
            .modal-box { padding: 15px; }
            .modal-box h3 { font-size: 16px; }
            .modal-footer button { font-size: 13px; padding: 6px 14px; }
            .reason-quick-btns button { font-size: 11px; padding: 3px 10px; }
        }
    </style>
</head>
<body>

    <!-- 顶部导航栏（统一汉堡结构，保留角色权限） -->
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

        <!-- 根据角色渲染不同菜单（保留原逻辑） -->
        <nav class="nav-links" id="navLinks">
            <?php if ($currentUser['role'] === 'student_admin'): ?>
                <a href="/admin/points.php" class="active">积分管理</a>
                <a href="/profile.php">个人主页</a>
            <?php else: ?>
                <a href="/admin/index.php">概览</a>
                <a href="/admin/points.php" class="active">积分管理</a>
                <a href="/admin/users.php">用户管理</a>
                <a href="/admin/homepage.php">首页管理</a>
                <a href="/admin/lottery.php">抽奖设置</a>
                <a href="/admin/settings.php">系统设置</a>
                <a href="/admin/logs.php">操作日志</a>
                <a href="/index.php">返回首页</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- 主内容区 -->
    <div class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>积分管理</h1>
                <p>班级积分、学生列表与加扣操作</p>
            </div>
        </div>

        <!-- 批量导入 CSV（仅教师） -->
        <?php if ($isTeacher): ?>
        <div class="card-box">
            <form method="post" enctype="multipart/form-data">
                <div class="form-csv-row">
                    <span style="font-weight:500;">📥 批量导入：</span>
                    <input type="file" name="excel_file" accept=".csv" required>
                    <button type="submit" name="action" value="upload_excel" class="btn-upload">上传 CSV</button>
                </div>
                <small class="text-muted">格式说明：学号或姓名, 变动分值（正数加分，负数减分）</small>
            </form>
        </div>
        <?php endif; ?>

        <!-- 学生积分列表（横向滚动） -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;">头像</th>
                        <th>姓名</th>
                        <th>学号</th>
                        <th>当前积分</th>
                        <th style="text-align:right;">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td>
                            <img src="<?= $s['avatar'] ? '../uploads/avatars/'.$s['avatar'] : '../assets/default-avatar.png' ?>" 
                                 class="user-avatar-sm" alt="头像">
                        </td>
                        <td style="font-weight:500;"><?= htmlspecialchars($s['realname']) ?></td>
                        <td style="color:#666;"><?= htmlspecialchars($s['username']) ?></td>
                        <td><span class="badge-point"><?= $s['points'] ?></span></td>
                        <td style="text-align:right;">
                            <div class="action-btns">
                                <button class="btn-action btn-add" onclick="openModal(<?= $s['id'] ?>, 'add', '<?= htmlspecialchars($s['realname']) ?>')">➕ 加分</button>
                                <button class="btn-action btn-sub" onclick="openModal(<?= $s['id'] ?>, 'subtract', '<?= htmlspecialchars($s['realname']) ?>')">➖ 减分</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== 输入操作模态框 ===== -->
    <div id="pointModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3 id="modalTitle">积分操作</h3>
            <div class="modal-body">
                <label for="modalPoints">变动分值 <span style="color:#6b7280;font-weight:400;">(输入正数)</span></label>
                <input type="number" id="modalPoints" min="1" placeholder="请输入分值，如: 5">
                
                <label>选择/输入操作原因</label>
                <div class="reason-quick-btns">
                    <button type="button" onclick="setReason('课堂表现优异')">课堂表现优异</button>
                    <button type="button" onclick="setReason('作业完成出色')">作业完成出色</button>
                    <button type="button" onclick="setReason('乐于助人')">乐于助人</button>
                    <button type="button" onclick="setReason('为班级做贡献')">为班级做贡献</button>
                </div>
                <textarea id="modalReason" rows="3" placeholder="点击上面按钮自动填入，或在此处自定义输入原因..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">取消</button>
                <button type="button" class="btn-confirm" onclick="submitPoints()">确认操作</button>
            </div>
        </div>
    </div>

    <!-- ===== 二次确认模态框 ===== -->
    <div id="confirmModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3 id="confirmTitle">确认操作</h3>
            <div class="modal-body">
                <p style="color:#6b7280; margin-bottom:10px;">请核对以下信息无误后再提交：</p>
                <div class="confirm-data-list">
                    <div class="confirm-data-item">
                        <span class="label">操作对象</span>
                        <span class="value" id="confirmStudentName">--</span>
                    </div>
                    <div class="confirm-data-item">
                        <span class="label">操作类型</span>
                        <span class="value" id="confirmActionType" style="color:#4f46e5;">--</span>
                    </div>
                    <div class="confirm-data-item">
                        <span class="label">变动分值</span>
                        <span class="value" id="confirmPoints">--</span>
                    </div>
                    <div class="confirm-data-item">
                        <span class="label">操作原因</span>
                        <span class="value" id="confirmReason" style="color:#4b5563;">--</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeConfirmModal()">返回修改</button>
                <button type="button" class="btn-confirm" onclick="confirmSubmit()">✔ 确认提交</button>
            </div>
        </div>
    </div>

    <!-- 隐藏表单 -->
    <form id="pointsForm" method="post" style="display:none;">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="user_id" id="formUserId">
        <input type="hidden" name="change_points" id="formPoints">
        <input type="hidden" name="reason" id="formReason">
    </form>

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

        // 积分操作逻辑（完全保留）
        let currentUserId = null;
        let currentAction = null;
        let currentStudentName = null;

        function openModal(userId, actionType, studentName) {
            currentUserId = userId;
            currentAction = actionType;
            currentStudentName = studentName;
            document.getElementById('modalPoints').value = '';
            document.getElementById('modalReason').value = '';
            const title = document.getElementById('modalTitle');
            title.innerText = actionType === 'add' ? '⭐ 执行加分操作' : '⚠️ 执行减分操作';
            document.getElementById('pointModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('pointModal').style.display = 'none';
            currentUserId = null;
            currentAction = null;
            currentStudentName = null;
        }

        function setReason(reason) {
            document.getElementById('modalReason').value = reason;
            document.getElementById('modalReason').focus();
        }

        function submitPoints() {
            const points = parseInt(document.getElementById('modalPoints').value);
            const reason = document.getElementById('modalReason').value.trim();
            if (isNaN(points) || points <= 0) {
                alert('请输入有效的正数分值！');
                return;
            }
            const actionText = currentAction === 'add' ? '➕ 加分' : '➖ 减分';
            document.getElementById('confirmStudentName').innerText = currentStudentName;
            document.getElementById('confirmActionType').innerText = actionText;
            document.getElementById('confirmPoints').innerText = points + ' 分';
            document.getElementById('confirmReason').innerText = reason || '未填写原因';
            document.getElementById('pointModal').style.display = 'none';
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
            document.getElementById('pointModal').style.display = 'flex';
        }

        function confirmSubmit() {
            const points = parseInt(document.getElementById('modalPoints').value);
            const reason = document.getElementById('modalReason').value.trim();
            document.getElementById('formAction').value = currentAction;
            document.getElementById('formUserId').value = currentUserId;
            document.getElementById('formPoints').value = points;
            document.getElementById('formReason').value = reason;
            document.getElementById('pointsForm').submit();
        }
    </script>

</body>
</html>
<?php require_once '../includes/admin_footer.php'; ?>