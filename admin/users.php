<?php
// ==========================================
// 1. PHP 后端数据逻辑 (保持你的原有逻辑)
// ==========================================
require_once '../includes/admin_header.php';
if (!$isTeacher) die('无权访问');

$message = '';
$error = '';

// ------ 添加学生 ------
if (isset($_POST['add_student'])) {
    $username = trim($_POST['username'] ?? '');
    $realname = trim($_POST['realname'] ?? '');
    $password = $_POST['password'] ?? '123456';
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($realname)) {
        $error = '学号和姓名不能为空';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = "学号 '{$username}' 已被占用";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, realname, password, email, role, points) VALUES (?, ?, ?, ?, 'student', 0)");
            $stmt->execute([$username, $realname, $hashed, $email ?: null]);
            $message = "学生 '{$realname}' 添加成功";
        }
    }
}

// ------ 删除用户 ------
if (isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid == $currentUser['id']) {
        $error = '不能删除自己的账号';
    } else {
        $userToDelete = $pdo->prepare("SELECT role, avatar FROM users WHERE id = ?");
        $userToDelete->execute([$uid]);
        $delUser = $userToDelete->fetch();
        if (!$delUser) {
            $error = '用户不存在';
        } elseif ($delUser['role'] === 'teacher') {
            $error = '不能删除教师账号';
        } else {
            if ($delUser['avatar'] && file_exists('../uploads/avatars/' . $delUser['avatar'])) {
                unlink('../uploads/avatars/' . $delUser['avatar']);
            }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $pdo->prepare("DELETE FROM points_log WHERE user_id = ?")->execute([$uid]);
            $message = '用户已删除';
        }
    }
}

// ------ 修改角色 ------
if (isset($_POST['change_role'])) {
    $uid = (int)$_POST['user_id'];
    $role = $_POST['role'];
    if (in_array($role, ['student','student_admin'])) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'teacher'");
        $stmt->execute([$role, $uid]);
    }
}

// ------ 批量导入 CSV ------
if (isset($_FILES['student_csv']) && $_FILES['student_csv']['error'] === UPLOAD_ERR_OK) {
    $fileType = strtolower(pathinfo($_FILES['student_csv']['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, ['csv', 'txt'])) {
        $error = '仅支持 CSV 或 TXT 格式的文件。请将 Excel 另存为 CSV（逗号分隔）再上传。';
    } else {
        $handle = fopen($_FILES['student_csv']['tmp_name'], 'r');
        if ($handle === false) {
            $error = '无法读取文件，请检查文件是否损坏。';
        } else {
            $import_count = 0;
            $skip_count = 0;
            while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if (empty($data) || (isset($data[0]) && in_array(trim($data[0]), ['学号','用户名','账号','username']))) {
                    continue;
                }
                if (count($data) >= 2) {
                    $username = trim($data[0] ?? '');
                    $realname = trim($data[1] ?? '');
                    $password = trim($data[2] ?? '123456');
                    if (!empty($username) && !empty($realname)) {
                        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $check->execute([$username]);
                        if (!$check->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO users (username, realname, password, role, points) VALUES (?, ?, ?, 'student', 0)");
                            $stmt->execute([$username, $realname, password_hash($password, PASSWORD_DEFAULT)]);
                            $import_count++;
                        } else {
                            $skip_count++;
                        }
                    }
                }
            }
            fclose($handle);
            if ($import_count === 0 && $skip_count === 0) {
                $error = '文件中未找到有效数据，请检查格式（学号,姓名,初始密码）';
            } else {
                $message = "导入完成：成功 {$import_count} 人，跳过重复 {$skip_count} 人";
            }
        }
    }
}

// ------ 搜索与分页 ------
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if (!empty($search)) {
    $where = " WHERE realname LIKE ? OR username LIKE ?";
    $params = ["%{$search}%", "%{$search}%"];
}

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users" . $where);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$stmt = $pdo->prepare("SELECT * FROM users" . $where . " ORDER BY role, realname LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeBuddy - 用户管理</title>
    <style>
        /* ==========================================
           CSS 样式重置与布局（完全同步后台仪表盘风格）
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

        /* --- 顶部导航栏 (含汉堡) --- */
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
            margin-left: 10px;  /* 与用户区隔开 */
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

        /* --- 消息提示 --- */
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

        /* --- 工具栏 --- */
        .toolbar-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.15s;
        }
        .toolbar-row .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-action {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-primary:hover { background: #4338ca; }
        .btn-secondary { background: #eef2ff; color: #4f46e5; }
        .btn-secondary:hover { background: #dbeafe; }

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
            width: 180px;
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

        /* --- 表格 --- */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            /* ---- 新增入场动画 ---- */
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.2s;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 600px;  /* 小于此宽横向滚动 */
        }
        th, td {
            padding: 16px 15px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        th { background: #f9fafb; color: #666; font-weight: normal; font-size: 13px; }
        tr:last-child td { border-bottom: none; }

        /* ---- 表格行逐行动画 ---- */
        .table-responsive tbody tr {
            opacity: 0;
            animation: fadeUp 0.4s ease forwards;
        }
        /* 为前15行设置不同的延迟（每页最多15条） */
        .table-responsive tbody tr:nth-child(1) { animation-delay: 0.05s; }
        .table-responsive tbody tr:nth-child(2) { animation-delay: 0.10s; }
        .table-responsive tbody tr:nth-child(3) { animation-delay: 0.15s; }
        .table-responsive tbody tr:nth-child(4) { animation-delay: 0.20s; }
        .table-responsive tbody tr:nth-child(5) { animation-delay: 0.25s; }
        .table-responsive tbody tr:nth-child(6) { animation-delay: 0.30s; }
        .table-responsive tbody tr:nth-child(7) { animation-delay: 0.35s; }
        .table-responsive tbody tr:nth-child(8) { animation-delay: 0.40s; }
        .table-responsive tbody tr:nth-child(9) { animation-delay: 0.45s; }
        .table-responsive tbody tr:nth-child(10) { animation-delay: 0.50s; }
        .table-responsive tbody tr:nth-child(11) { animation-delay: 0.55s; }
        .table-responsive tbody tr:nth-child(12) { animation-delay: 0.60s; }
        .table-responsive tbody tr:nth-child(13) { animation-delay: 0.65s; }
        .table-responsive tbody tr:nth-child(14) { animation-delay: 0.70s; }
        .table-responsive tbody tr:nth-child(15) { animation-delay: 0.75s; }

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
        .badge-teacher {
            display: inline-block;
            background: #f3f4f6;
            color: #6b7280;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .role-select {
            padding: 4px 10px;
            border: 1px solid #d1d5db;
            border-radius: 20px;
            font-size: 13px;
            outline: none;
            background: white;
            cursor: pointer;
        }
        .role-select:focus { border-color: #4f46e5; }

        .btn-delete {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            border: 1px solid #ef4444;
            color: #ef4444;
            background: transparent;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-delete:hover { background: #fef2f2; border-color: #dc2626; color: #dc2626; }

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
            width: 440px;
            max-width: 90%;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            animation: fadeIn 0.2s ease-out;
        }
        @keyframes fadeIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-box h3 { font-size: 18px; margin-bottom: 15px; color: #1f2937; }
        .modal-body label { display: block; font-size: 13px; font-weight: 500; color: #4b5563; margin-top: 12px; margin-bottom: 4px; }
        .modal-body input[type="text"],
        .modal-body input[type="email"],
        .modal-body input[type="file"] {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }
        .modal-body input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
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
        .btn-action,
        .btn-search,
        .btn-delete,
        .modal-footer button,
        .pagination-custom li a {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s, color 0.2s, border-color 0.2s;
        }
        .top-navbar .nav-links a:hover,
        .top-navbar .btn-logout:hover,
        .btn-action:hover,
        .btn-search:hover,
        .btn-delete:hover,
        .modal-footer button:hover,
        .pagination-custom li a:hover {
            transform: scale(1.03);
        }
        .btn-action:active,
        .btn-search:active,
        .btn-delete:active,
        .modal-footer button:active,
        .pagination-custom li a:active {
            transform: scale(0.97);
        }

        /* ==========================================
           响应式适配（与后台仪表盘完全一致）
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
            .toolbar-row { flex-direction: column; align-items: stretch; }
            .search-form { width: 100%; }
            .input-search { flex: 1; width: auto; }
            .modal-box { width: 95%; padding: 20px; }
        }

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
            .user-avatar-sm { width: 28px; height: 28px; }
            .badge-point { font-size: 13px; padding: 2px 10px; }
            .btn-delete { font-size: 12px; padding: 4px 10px; }
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
            .btn-action { font-size: 13px; padding: 6px 12px; }
            .input-search { font-size: 13px; padding: 6px 10px; }
            .btn-search { padding: 0 12px; font-size: 13px; }
            table { font-size: 12px; min-width: 360px; }
            th, td { padding: 6px 4px; }
            .user-avatar-sm { width: 22px; height: 22px; }
            .badge-point { font-size: 12px; padding: 2px 8px; }
            .role-select { font-size: 12px; padding: 2px 8px; }
            .btn-delete { font-size: 11px; padding: 3px 8px; }
            .modal-box { padding: 15px; }
            .modal-box h3 { font-size: 16px; }
            .modal-footer button { font-size: 13px; padding: 6px 14px; }
            .pagination-custom li a { padding: 6px 10px; font-size: 13px; }
        }
    </style>
</head>
<body>

    <!-- 顶部导航栏（与后台仪表盘完全一致） -->
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
            <a href="/admin/users.php" class="active">用户管理</a>
            <a href="/admin/homepage.php">首页管理</a>
            <a href="/admin/lottery.php">抽奖设置</a>
            <a href="/admin/settings.php">系统设置</a>
            <a href="/admin/logs.php">操作日志</a>
            <a href="/index.php">返回首页</a>
        </nav>
    </header>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>用户管理</h1>
                <p>学生列表、角色与账号维护</p>
            </div>
        </div>

        <!-- 消息提示 -->
        <?php if ($message): ?><div class="msg-box msg-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="msg-box msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- 工具栏 -->
        <div class="toolbar-row">
            <div class="actions">
                <button class="btn-action btn-primary" onclick="openModal('addModal')">➕ 添加学生</button>
                <button class="btn-action btn-secondary" onclick="openModal('importModal')">📥 批量导入</button>
            </div>
            <form class="search-form" method="get">
                <input type="text" name="search" class="input-search" placeholder="搜索姓名或学号" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-search">🔍</button>
            </form>
        </div>

        <!-- 表格 -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;">头像</th>
                        <th>姓名</th>
                        <th>学号</th>
                        <th>邮箱</th>
                        <th>角色</th>
                        <th>积分</th>
                        <th style="width:80px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <img src="<?= $u['avatar'] ? '../uploads/avatars/'.$u['avatar'] : '../assets/default-avatar.png' ?>" 
                                 class="user-avatar-sm" alt="头像">
                        </td>
                        <td style="font-weight:500;"><?= htmlspecialchars($u['realname']) ?></td>
                        <td style="color:#666;"><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                        <td>
                            <?php if ($u['role'] === 'teacher'): ?>
                                <span class="badge-teacher">教师</span>
                            <?php else: ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" onchange="this.form.submit()" class="role-select">
                                        <option value="student" <?= $u['role']=='student'?'selected':'' ?>>学生</option>
                                        <option value="student_admin" <?= $u['role']=='student_admin'?'selected':'' ?>>积分管理员</option>
                                    </select>
                                    <input type="hidden" name="change_role" value="1">
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge-point"><?= $u['points'] ?></span></td>
                        <td>
                            <?php if ($u['role'] !== 'teacher'): ?>
                                <button class="btn-delete" onclick="openDeleteModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['realname']) ?>')">删除</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
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

    <!-- 添加学生模态框 -->
    <div id="addModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3>添加学生</h3>
            <form method="post">
                <div class="modal-body">
                    <label>学号 <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="username" required>
                    
                    <label>姓名 <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="realname" required>
                    
                    <label>初始密码</label>
                    <input type="text" name="password" value="123456">
                    
                    <label>邮箱（选填）</label>
                    <input type="email" name="email">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('addModal')">取消</button>
                    <button type="submit" name="add_student" class="btn-confirm">确认添加</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 批量导入模态框 -->
    <div id="importModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3>批量导入学生</h3>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <label>上传 CSV / TXT 文件</label>
                    <input type="file" name="student_csv" accept=".csv,.txt" required>
                    <div style="font-size:13px; color:#6b7280; margin-top:10px; line-height:1.6;">
                        <strong>格式要求：</strong><br>
                        每行格式：<code>学号,姓名,初始密码</code><br>
                        <span style="font-size:12px; color:#9ca3af;">Excel 用户请先“另存为 → CSV UTF-8 (逗号分隔)”再上传。</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('importModal')">取消</button>
                    <button type="submit" class="btn-confirm">开始导入</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div id="deleteModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3 style="color:#dc2626;">⚠️ 确认删除</h3>
            <div class="modal-body">
                <p style="margin-bottom:15px; color:#4b5563;">
                    确定要永久删除该用户吗？此操作将同时清除其积分日志，且<strong>不可恢复</strong>。
                </p>
                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:10px 15px; display:flex; justify-content:space-between; font-size:14px;">
                    <span style="color:#6b7280;">目标用户：</span>
                    <span id="deleteUserName" style="font-weight:600; color:#1f2937;">--</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('deleteModal')">取消</button>
                <button type="button" class="btn-danger" onclick="confirmDelete()">确认删除</button>
            </div>
        </div>
    </div>

    <!-- 隐式删除表单 -->
    <form id="deleteForm" method="post" style="display:none;">
        <input type="hidden" name="delete_user" value="1">
        <input type="hidden" name="user_id" id="delete_user_id">
    </form>

    <script>
        // 模态框控制
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });
        });

        // 删除专用
        function openDeleteModal(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').innerText = userName;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        function confirmDelete() {
            document.getElementById('deleteForm').submit();
        }

        // 汉堡菜单
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