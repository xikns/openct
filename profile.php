<?php
require_once 'includes/header.php';
requireLogin();
$user = currentUser();

// 只对教师跳转，允许 student_admin 使用个人中心
if ($user['role'] === 'teacher') redirect('admin/index.php');

$message = '';
$error = '';
$website_title = getConfig('website_title') ?: '班级积分管理系统';

// 修改密码
if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    if (!password_verify($old, $user['password'])) {
        $message = '原密码错误';
    } elseif (strlen($new) < 6) {
        $message = '新密码至少6位';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        $message = '密码修改成功';
        $user = currentUser(); // 刷新 session
    }
}

// 设置/修改保密问题
if (isset($_POST['save_secret'])) {
    $question = trim($_POST['secret_question'] ?? '');
    $answer = trim($_POST['secret_answer'] ?? '');
    if (empty($question) || empty($answer)) {
        $error = '请选择保密问题并填写答案';
    } elseif (strlen($answer) < 2) {
        $error = '答案至少2个字符';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET secret_question = ?, secret_answer_hash = ? WHERE id = ?");
        $stmt->execute([$question, password_hash($answer, PASSWORD_DEFAULT), $user['id']]);
        $message = '保密问题设置成功';
        $user = currentUser();
    }
}

// 上传头像
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','gif'];
    if (!in_array(strtolower($ext), $allowed)) {
        $message = '不允许的文件类型（仅允许 jpg/png/gif）';
    } else {
        $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], 'uploads/avatars/' . $filename);
        if ($user['avatar'] && file_exists('uploads/avatars/' . $user['avatar'])) {
            unlink('uploads/avatars/' . $user['avatar']);
        }
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$filename, $user['id']]);
        $message = '头像更新成功';
        $user = currentUser();
    }
}

// 积分变动记录
$logs = $pdo->prepare("
    SELECT pl.changed_points, pl.reason, pl.created_at, op.realname as operator_name 
    FROM points_log pl 
    LEFT JOIN users op ON pl.operator_id = op.id 
    WHERE pl.user_id = ? 
    ORDER BY pl.created_at DESC 
    LIMIT 20
");
$logs->execute([$user['id']]);
$logs = $logs->fetchAll();

// 预定义保密问题列表
$secretQuestions = [
    '您母亲的姓名是？',
    '您父亲的姓名是？',
    '您小学的校名是？',
    '您最喜欢的颜色是？',
    '您的出生地是？',
    '您最好的朋友的名字是？'
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($website_title) ?> · 个人中心</title>
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
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.1s;
        }
        .msg-success { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .msg-error { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .msg-info { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }

        /* --- 卡片 --- */
        .card-box {
            background: white;
            border-radius: 10px;
            padding: 25px 30px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            opacity: 0;
            animation: fadeUp 0.5s ease forwards;
            animation-delay: 0.15s;
        }
        .card-box hr {
            border: 0;
            border-top: 1px solid #f3f4f6;
            margin: 20px 0;
        }

        /* --- 表单 --- */
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 4px;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
        }
        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            background: white;
            cursor: pointer;
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
        .btn-outline {
            padding: 6px 16px;
            border-radius: 6px;
            border: 1px solid #4f46e5;
            background: transparent;
            color: #4f46e5;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-outline:hover { background: #eef2ff; }

        /* --- 头像与积分 --- */
        .profile-avatar {
            text-align: center;
            padding: 10px 0;
        }
        .profile-avatar img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(79,70,229,0.15);
            transition: border-color 0.3s;
        }
        .profile-avatar img:hover {
            border-color: rgba(79,70,229,0.35);
        }
        .profile-avatar .points-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-top: 6px;
        }
        .profile-avatar .points-label {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
            margin-top: 12px;
        }
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-upload-wrapper .file-label {
            display: block;
            padding: 8px 12px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            transition: all 0.3s;
            background: #f9fafb;
            cursor: pointer;
        }
        .file-upload-wrapper .file-label:hover {
            border-color: #4f46e5;
            background: rgba(79,70,229,0.04);
        }
        .file-upload-wrapper .file-label.has-file {
            border-color: #4f46e5;
            background: rgba(79,70,229,0.06);
            color: #4f46e5;
        }

        /* --- 积分记录表格 --- */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 380px;
        }
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        th {
            background: #f9fafb;
            color: #6b7280;
            font-weight: 500;
            font-size: 13px;
        }
        tr:last-child td { border-bottom: none; }
        .text-up { color: #22c55e; font-weight: 600; }
        .text-down { color: #ef4444; font-weight: 600; }
        .text-muted { color: #9ca3af; }
        .text-center { text-align: center; }
        .py-4 { padding: 20px 0; }

        /* ==========================================
           动画与交互增强
           ========================================== */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

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

        .top-navbar .nav-links a,
        .top-navbar .btn-logout,
        .btn-primary,
        .btn-outline {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s, color 0.2s, border-color 0.2s;
        }
        .top-navbar .nav-links a:hover,
        .top-navbar .btn-logout:hover,
        .btn-primary:hover,
        .btn-outline:hover {
            transform: scale(1.03);
        }
        .btn-primary:active,
        .btn-outline:active {
            transform: scale(0.97);
        }

        /* 保密问题提示文本 */
        .current-secret {
            font-size: 13px;
            color: #6b7280;
            margin-top: 10px;
            background: #f9fafb;
            padding: 6px 12px;
            border-radius: 6px;
        }

        /* ==========================================
           响应式适配
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
            .card-box { padding: 20px; }
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
            .profile-avatar img { width: 90px; height: 90px; }
            .profile-avatar .points-number { font-size: 1.6rem; }
            .form-control { font-size: 13px; padding: 6px 10px; }
            .btn-primary { width: 100%; text-align: center; }
            .btn-outline { width: 100%; text-align: center; }
            table { font-size: 13px; min-width: 320px; }
            th, td { padding: 8px 6px; }
            .msg-box { font-size: 13px; padding: 10px 14px; }
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
            .profile-avatar img { width: 75px; height: 75px; }
            .profile-avatar .points-number { font-size: 1.4rem; }
            table { font-size: 12px; min-width: 280px; }
            th, td { padding: 6px 4px; }
            .file-upload-wrapper .file-label { font-size: 13px; padding: 6px 10px; }
        }
    </style>
</head>
<body>

    <!-- 顶部导航栏（与后台完全一致） -->
    <header class="top-navbar" id="topNavbar">
        <div class="logo-area">
            <img src="/static/picture/ailogo.png" alt="Logo">
        </div>
        
        <div class="user-area">
            <div class="user-avatar"><?= mb_substr($user['realname'], 0, 1) ?></div>
            <span><?= htmlspecialchars($user['realname']) ?></span>
            <a href="/admin/logout.php" class="btn-logout">退出</a>
        </div>

        <button class="navbar-toggler" id="navbarToggler" aria-label="切换导航">
            ☰
        </button>

        <nav class="nav-links" id="navLinks">
            <?php if ($user['role'] === 'student_admin'): ?>
                <a href="/admin/points.php">积分管理</a>
                <a href="/profile.php" class="active">个人中心</a>
            <?php else: ?>
                <a href="/index.php">首页</a>
                <a href="/rankings.php">排行榜</a>
                <a href="/profile.php" class="active">个人中心</a>
            <?php endif; ?>
            <a href="https://xikn.rf.gd" target="_blank">汐科博客</a>
            <a href="https://xikexinxi.mysxl.cn" target="_blank">汐科信息工作室</a>
        </nav>
    </header>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1>个人中心</h1>
                <p>头像、密码、保密问题与积分记录</p>
            </div>
        </div>

        <!-- 消息提示 -->
        <?php if ($message): ?>
            <?php 
                $msgClass = 'msg-info';
                if (strpos($message, '成功') !== false || strpos($message, '更新') !== false) {
                    $msgClass = 'msg-success';
                } elseif (strpos($message, '错误') !== false || strpos($message, '至少') !== false || strpos($message, '不允许') !== false) {
                    $msgClass = 'msg-error';
                }
            ?>
            <div class="msg-box <?= $msgClass ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="msg-box msg-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- 主卡片 -->
        <div class="card-box">
            <div class="row g-4">
                <!-- 左侧：头像 + 积分 + 上传 -->
                <div class="col-md-4">
                    <div class="profile-avatar">
                        <img src="<?= $user['avatar'] ? 'uploads/avatars/'.$user['avatar'] : 'assets/default-avatar.png' ?>" alt="头像">
                        <div class="points-number"><?= $user['points'] ?></div>
                        <div class="points-label">当前积分</div>

                        <form method="post" enctype="multipart/form-data" class="mt-3">
                            <div class="file-upload-wrapper">
                                <input type="file" name="avatar" accept="image/*" id="avatarInput">
                                <label for="avatarInput" class="file-label" id="fileLabel">📷 选择新头像</label>
                            </div>
                            <button type="submit" class="btn-outline mt-2" style="width:100%;">更新头像</button>
                        </form>
                    </div>
                </div>

                <!-- 右侧：密码修改 + 保密问题 + 积分记录 -->
                <div class="col-md-8">
                    <!-- 修改密码 -->
                    <h5 style="font-size:16px; font-weight:600; color:#1f2937; margin-bottom:12px;">🔒 修改密码</h5>
                    <form method="post">
                        <div class="form-group">
                            <input type="password" name="old_password" class="form-control" placeholder="原密码" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="new_password" class="form-control" placeholder="新密码（至少6位）" required minlength="6">
                        </div>
                        <button type="submit" name="change_password" class="btn-primary">修改密码</button>
                    </form>

                    <hr>

                    <!-- 设置保密问题 -->
                    <h5 style="font-size:16px; font-weight:600; color:#1f2937; margin-bottom:12px;">🛡️ 保密问题（用于密码找回）</h5>
                    <form method="post">
                        <div class="form-group">
                            <label for="secret_question">选择保密问题</label>
                            <select name="secret_question" id="secret_question" class="form-select">
                                <option value="">-- 请选择 --</option>
                                <?php foreach ($secretQuestions as $q): ?>
                                    <option value="<?= htmlspecialchars($q) ?>" <?= ($user['secret_question'] ?? '') == $q ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($q) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="secret_answer">输入答案</label>
                            <input type="text" name="secret_answer" id="secret_answer" class="form-control" placeholder="请输入答案" value="">
                        </div>
                        <button type="submit" name="save_secret" class="btn-primary">保存保密问题</button>
                    </form>

                    <?php if (!empty($user['secret_question'])): ?>
                        <div class="current-secret">
                            📌 当前保密问题：<?= htmlspecialchars($user['secret_question']) ?>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <!-- 积分变动记录 -->
                    <h5 style="font-size:16px; font-weight:600; color:#1f2937; margin-bottom:12px;">📊 积分变动记录</h5>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:70px;">变动</th>
                                    <th>原因</th>
                                    <th style="width:80px;">操作人</th>
                                    <th style="width:130px;">时间</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($logs): ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td class="<?= $l['changed_points'] >= 0 ? 'text-up' : 'text-down' ?>">
                                            <?= $l['changed_points'] > 0 ? '+' . $l['changed_points'] : $l['changed_points'] ?>
                                        </td>
                                        <td><?= htmlspecialchars($l['reason'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($l['operator_name'] ?? '系统') ?></td>
                                        <td style="color:#6b7280; font-size:0.85rem;">
                                            <?= date('m-d H:i', strtotime($l['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">暂无积分变动记录</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

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

        // 头像文件上传提示
        document.addEventListener('DOMContentLoaded', function() {
            var fileInput = document.getElementById('avatarInput');
            var fileLabel = document.getElementById('fileLabel');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        fileLabel.textContent = '📎 ' + this.files[0].name;
                        fileLabel.classList.add('has-file');
                    } else {
                        fileLabel.textContent = '📷 选择新头像';
                        fileLabel.classList.remove('has-file');
                    }
                });
            }
        });
    </script>

</body>
</html>