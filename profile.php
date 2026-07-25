<?php
require_once 'includes/header.php';
requireLogin();
$user = currentUser();

// 只对教师跳转（保留原有逻辑），允许 student_admin 使用个人中心
if ($user['role'] === 'teacher') redirect('admin/index.php');

$message = '';
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

// 上传头像
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','gif'];
    if (!in_array(strtolower($ext), $allowed)) {
        $message = '不允许的文件类型（仅允许 jpg/png/gif）';
    } else {
        $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], 'uploads/avatars/' . $filename);
        // 删除旧头像（如果不是默认头像）
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
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($website_title) ?> · 个人中心</title>
    <link rel="stylesheet" href="static/css/quick-website.css" id="stylesheet">
    <style>
        /* ===== 全局背景 ===== */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== 隐藏导航栏中的“登录”链接 ===== */
        .navbar a[href="login.php"] {
            display: none !important;
        }

        /* ===== 顶部标题区域 ===== */
        .profile-header {
            text-align: center;
            padding: 30px 20px 10px;
        }
        .profile-header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            word-break: break-word;
            margin-bottom: 0.3rem;
            letter-spacing: -0.5px;
        }
        .profile-header p {
            color: #6b7280;
            font-size: 1.1rem;
            margin-top: 4px;
            font-weight: 400;
            letter-spacing: 1px;
        }

        /* ===== 卡片（毛玻璃效果） ===== */
        .card-apple {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 20px rgba(0, 0, 0, 0.02);
            transition: box-shadow 0.3s ease;
            overflow: hidden;
        }
        .card-apple:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.12);
        }

        /* ===== 头像区域 ===== */
        .avatar-wrapper {
            padding: 30px 20px;
            text-align: center;
        }
        .avatar-wrapper .avatar-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(79, 70, 229, 0.15);
            transition: border-color 0.3s;
        }
        .avatar-wrapper .avatar-img:hover {
            border-color: rgba(79, 70, 229, 0.35);
        }
        .avatar-wrapper .points-display {
            font-size: 2.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-top: 8px;
        }
        .avatar-wrapper .points-label {
            font-size: 0.9rem;
            color: #6b7280;
        }

        /* ===== 文件上传 ===== */
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-input-wrapper .file-label {
            display: block;
            padding: 10px 16px;
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            transition: all 0.3s;
            background: rgba(249, 250, 251, 0.5);
            cursor: pointer;
        }
        .file-input-wrapper .file-label:hover {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.04);
        }
        .file-input-wrapper .file-label.has-file {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.06);
            color: #4f46e5;
        }

        /* ===== 表单 ===== */
        .form-apple {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 14px 20px;
            font-size: 16px;
            background: rgba(249, 250, 251, 0.8);
            transition: all 0.25s ease;
            outline: none;
            box-sizing: border-box;
            width: 100%;
        }
        .form-apple:focus {
            border-color: #4f46e5;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
        }

        .btn-apple {
            display: inline-block;
            border: none;
            border-radius: 40px;
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
            box-sizing: border-box;
        }
        .btn-apple.primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            box-shadow: 0 6px 24px rgba(79, 70, 229, 0.30);
        }
        .btn-apple.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 32px rgba(79, 70, 229, 0.40);
        }
        .btn-apple.primary:active {
            transform: translateY(0);
        }
        .btn-apple.outline {
            background: transparent;
            color: #4f46e5;
            border: 2px solid #4f46e5;
            box-shadow: none;
        }
        .btn-apple.outline:hover {
            background: rgba(79, 70, 229, 0.06);
            transform: translateY(-2px);
        }
        .btn-apple.sm {
            padding: 8px 18px;
            font-size: 13px;
            border-radius: 30px;
        }

        /* ===== 消息提示 ===== */
        .msg-box {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            border: 1px solid transparent;
        }
        .msg-info {
            background: #eef2ff;
            color: #4f46e5;
            border-color: #c7d2fe;
        }
        .msg-success {
            background: #f0fdf4;
            color: #15803d;
            border-color: #bbf7d0;
        }
        .msg-error {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        /* ===== 积分记录表格 ===== */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 380px;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        th {
            background: rgba(249, 250, 251, 0.6);
            color: #6b7280;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:last-child td { border-bottom: none; }
        .text-up { color: #22c55e; font-weight: 600; }
        .text-down { color: #ef4444; font-weight: 600; }

        /* ===== 右侧内容区域 ===== */
        .content-wrapper {
            padding: 30px 30px 30px 10px;
        }
        .content-wrapper h5 {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 14px;
        }
        .content-wrapper hr {
            border: 0;
            border-top: 1px solid #f1f3f5;
            margin: 20px 0;
        }

        /* ===== 底部版权 ===== */
        .profile-footer {
            text-align: center;
            padding: 30px 20px 20px;
            color: #9ca3af;
            font-size: 0.9rem;
            line-height: 1.8;
        }
        .profile-footer a {
            color: #4f46e5;
            text-decoration: none;
        }
        .profile-footer a:hover {
            text-decoration: underline;
        }

        /* ==========================================
           响应式适配
           ========================================== */

        @media (max-width: 767.98px) {
            .profile-header h1 {
                font-size: 2.2rem;
            }
            .profile-header p {
                font-size: 0.95rem;
            }
            .profile-header {
                padding: 20px 15px 5px;
            }
            .card-apple {
                border-radius: 24px;
            }
            .avatar-wrapper {
                padding: 25px 15px 10px;
                border-right: none !important;
                border-bottom: 1px solid rgba(0,0,0,0.05);
            }
            .avatar-wrapper .avatar-img {
                width: 90px;
                height: 90px;
            }
            .avatar-wrapper .points-display {
                font-size: 1.8rem;
            }
            .content-wrapper {
                padding: 20px 18px;
            }
            .content-wrapper h5 {
                font-size: 15px;
            }
            .form-apple {
                font-size: 15px;
                padding: 12px 16px;
                border-radius: 14px;
            }
            .btn-apple {
                font-size: 15px;
                padding: 12px 20px;
                width: 100%;
            }
            .btn-apple.sm {
                font-size: 13px;
                padding: 8px 16px;
                width: auto;
            }
            table {
                font-size: 13px;
                min-width: 320px;
            }
            th, td {
                padding: 8px 8px;
            }
            .msg-box {
                font-size: 13px;
                padding: 10px 14px;
            }
            .container.py-4 {
                padding-top: 1rem !important;
                padding-bottom: 0.5rem !important;
            }
            .profile-footer {
                font-size: 0.8rem;
                padding: 20px 10px;
            }
        }

        @media (max-width: 575.98px) {
            .profile-header h1 {
                font-size: 1.8rem;
            }
            .profile-header p {
                font-size: 0.85rem;
            }
            .card-apple {
                border-radius: 20px;
            }
            .avatar-wrapper .avatar-img {
                width: 75px;
                height: 75px;
            }
            .avatar-wrapper .points-display {
                font-size: 1.5rem;
            }
            .content-wrapper {
                padding: 16px 12px;
            }
            .form-apple {
                font-size: 14px;
                padding: 10px 14px;
                border-radius: 12px;
            }
            .btn-apple {
                font-size: 14px;
                padding: 10px 16px;
                border-radius: 30px;
            }
            .btn-apple.sm {
                font-size: 12px;
                padding: 6px 14px;
            }
            table {
                font-size: 12px;
                min-width: 280px;
            }
            th, td {
                padding: 6px 6px;
            }
            .file-input-wrapper .file-label {
                font-size: 13px;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>

    <!-- ===== 顶部标题区域 ===== -->
    <div class="profile-header">
        <h1><?= htmlspecialchars($website_title) ?></h1>
        <p>积分管理系统 · 个人中心</p>
    </div>

    <!-- ===== 主卡片 ===== -->
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="card-apple">
                    <div class="row g-0">
                        <!-- 左侧：头像 + 积分 + 上传 -->
                        <div class="col-md-4 avatar-wrapper border-end">
                            <img src="<?= $user['avatar'] ? 'uploads/avatars/'.$user['avatar'] : 'assets/default-avatar.png' ?>" 
                                 class="avatar-img mb-3" alt="头像">
                            <div class="points-display"><?= $user['points'] ?></div>
                            <div class="points-label">当前积分</div>

                            <form method="post" enctype="multipart/form-data" class="mt-3">
                                <div class="file-input-wrapper">
                                    <input type="file" name="avatar" accept="image/*" id="avatarInput">
                                    <label for="avatarInput" class="file-label" id="fileLabel">📷 点击选择新头像</label>
                                </div>
                                <button type="submit" class="btn-apple outline sm mt-2 w-100">更新头像</button>
                            </form>
                        </div>

                        <!-- 右侧：密码修改 + 积分记录 -->
                        <div class="col-md-8 content-wrapper">
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

                            <!-- 修改密码 -->
                            <h5>🔒 修改密码</h5>
                            <form method="post">
                                <div class="mb-2">
                                    <input type="password" name="old_password" class="form-apple" placeholder="原密码" required>
                                </div>
                                <div class="mb-2">
                                    <input type="password" name="new_password" class="form-apple" placeholder="新密码（至少6位）" required minlength="6">
                                </div>
                                <button type="submit" name="change_password" class="btn-apple primary">修改密码</button>
                            </form>

                            <hr>

                            <!-- 积分变动记录 -->
                            <h5>📊 积分变动记录</h5>
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
                                            <td colspan="4" style="text-align:center; color:#9ca3af; padding:20px 0;">
                                                暂无积分变动记录
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 底部版权信息 ===== -->
    <div class="profile-footer">
        技术支持 © 2025-2026 <a href="http://xikexinxi.mysxl.cn" target="_blank">汐科信息工作室</a>. All Rights Reserved.<br>
        Powered By <a href="http://xikn.rf.gd/" target="_blank">汐科的博客</a>.
    </div>

    <!-- ===== 移除导航栏中的“登录”链接 ===== -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var navLinks = document.querySelectorAll('.navbar a, .nav-link');
            navLinks.forEach(function(link) {
                var href = link.getAttribute('href');
                var text = link.textContent.trim();
                if (text === '登录' || (href && href.includes('login.php'))) {
                    link.parentNode.removeChild(link);
                }
            });
        });
    </script>

    <!-- ===== 头像文件上传提示 ===== -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var fileInput = document.getElementById('avatarInput');
            var fileLabel = document.getElementById('fileLabel');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        fileLabel.textContent = '📎 ' + this.files[0].name;
                        fileLabel.classList.add('has-file');
                    } else {
                        fileLabel.textContent = '📷 点击选择新头像';
                        fileLabel.classList.remove('has-file');
                    }
                });
            }
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>