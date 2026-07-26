<?php
// ==========================================
// 1. PHP 后端数据逻辑 (完全保留，未改动)
// ==========================================
require_once '../includes/admin_header.php';
if (!$isTeacher) die('无权访问');

$message = '';

// 更新首页信息
if (isset($_POST['update_home'])) {
    $stmt = $pdo->prepare("UPDATE config SET config_value = ? WHERE config_key = ?");
    $stmt->execute([$_POST['class_name'], 'class_name']);
    $stmt->execute([$_POST['points_start'], 'points_start']);
    $stmt->execute([$_POST['points_end'], 'points_end']);
    $message = '首页信息已更新';
}

// 上传幻灯片
if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['slide_image']['name'], PATHINFO_EXTENSION);
    $filename = 'slide_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['slide_image']['tmp_name'], '../uploads/slides/' . $filename);
    $sort = (int)$_POST['sort_order'];
    $stmt = $pdo->prepare("INSERT INTO slides (image_url, sort_order) VALUES (?, ?)");
    $stmt->execute([$filename, $sort]);
    $message = '图片已上传';
}

// 删除幻灯片
if (isset($_GET['delete_slide'])) {
    $id = (int)$_GET['delete_slide'];
    $img = $pdo->query("SELECT image_url FROM slides WHERE id = $id")->fetchColumn();
    if ($img && file_exists('../uploads/slides/'.$img)) unlink('../uploads/slides/'.$img);
    $pdo->exec("DELETE FROM slides WHERE id = $id");
    $message = '图片已删除';
}

$class_name = getConfig('class_name');
$points_start = getConfig('points_start');
$points_end = getConfig('points_end');
$slides = $pdo->query("SELECT * FROM slides ORDER BY sort_order")->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeBuddy - 首页编辑</title>
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
        }
        .msg-success { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }

        /* --- 卡片 --- */
        .card-box {
            background: white;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .card-box h3 { font-size: 16px; margin-bottom: 15px; color: #1f2937; }

        /* --- 表单 --- */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-group {
            flex: 1;
            min-width: 180px;
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
        .btn-secondary {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-secondary:hover { background: #e5e7eb; }
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
            text-align: center;
        }
        .btn-danger-outline:hover { background: #fef2f2; border-color: #dc2626; color: #dc2626; }

        /* --- 上传行 --- */
        .upload-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 20px;
        }

        /* --- 图片网格 --- */
        .image-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        .image-card {
            background: #f9fafb;
            border: 1px solid #f3f4f6;
            border-radius: 8px;
            padding: 10px;
            width: 150px;
            text-align: center;
            transition: 0.2s;
        }
        .image-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .image-card img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .image-card .btn-danger-outline { width: 100%; }

        /* ==========================================
           新增：平滑进入 & 切换动画
           ========================================== */

        /* 1. 全局淡入上移关键帧 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 2. 主内容区整体入场 */
        .main-content {
            animation: fadeInUp 0.6s ease forwards;
        }

        /* 3. 卡片交错出现（两个卡片） */
        .card-box:nth-of-type(1) {
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: 0.1s;
        }
        .card-box:nth-of-type(2) {
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: 0.25s;
        }

        /* 4. 图片网格卡片逐个错开（适用于多个 .image-card） */
        .image-card {
            opacity: 0;
            animation: fadeInUp 0.5s ease forwards;
        }
        .image-card:nth-child(1) { animation-delay: 0.1s; }
        .image-card:nth-child(2) { animation-delay: 0.2s; }
        .image-card:nth-child(3) { animation-delay: 0.3s; }
        .image-card:nth-child(4) { animation-delay: 0.4s; }
        .image-card:nth-child(5) { animation-delay: 0.5s; }
        /* 若超过5个，后续继续延迟递增，但这里暂不写更多，可自行扩展 */

        /* 5. 预览首页按钮也加入动画（延迟稍后） */
        .btn-secondary {
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
            animation-delay: 0.4s;
        }

        /* 6. 消息提示框也加入淡入 */
        .msg-box {
            opacity: 0;
            animation: fadeInUp 0.5s ease forwards;
            animation-delay: 0.05s;
        }

        /* 7. 导航菜单展开/收起过渡增强（已有 transition，此处微调） */
        .top-navbar .nav-links {
            transition: max-height 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        opacity 0.4s ease,
                        visibility 0s linear 0.4s,
                        padding 0.3s ease;
            padding: 0 10px;
        }
        .top-navbar .nav-links.open {
            visibility: visible;
            max-height: 600px;
            opacity: 1;
            transition: max-height 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94),
                        opacity 0.3s ease,
                        visibility 0s linear 0s,
                        padding 0.3s ease;
            padding: 12px 10px;
        }

        /* 8. 按钮悬停放大微动 */
        .btn-primary,
        .btn-secondary,
        .btn-danger-outline {
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s, border-color 0.2s;
        }
        .btn-primary:hover,
        .btn-secondary:hover,
        .btn-danger-outline:hover {
            transform: scale(1.02);
        }
        .btn-primary:active,
        .btn-secondary:active {
            transform: scale(0.98);
        }

        /* ==========================================
           响应式适配（完全保留原样，未改动）
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
            .card-box { padding: 16px 18px; }
            .form-group { min-width: 140px; }
            .upload-row .form-group { min-width: 100%; }
            .image-card { width: 130px; }
            .image-card img { height: 85px; }
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
            .form-row { flex-direction: column; gap: 12px; }
            .form-group { min-width: auto; }
            .upload-row { flex-direction: column; align-items: stretch; }
            .upload-row .form-group { flex: auto; }
            .upload-row .btn-primary { width: 100%; }
            .image-grid { justify-content: center; }
            .image-card { width: 160px; }
            .image-card img { height: 100px; }
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
            .card-box { padding: 12px 14px; }
            .form-control { font-size: 13px; padding: 6px 10px; }
            .btn-primary { padding: 6px 14px; font-size: 13px; width: 100%; }
            .btn-secondary { font-size: 13px; padding: 6px 14px; }
            .image-card { width: 140px; }
            .image-card img { height: 85px; }
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
            <a href="/admin/homepage.php" class="active">首页管理</a>
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
                <h1>首页编辑</h1>
                <p>班级信息与轮播图管理</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="msg-box msg-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- 班级信息 -->
        <div class="card-box">
            <h3>班级信息</h3>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>班级名称</label>
                        <input type="text" name="class_name" class="form-control" value="<?= htmlspecialchars($class_name) ?>">
                    </div>
                    <div class="form-group">
                        <label>积分开始时间</label>
                        <input type="datetime-local" name="points_start" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($points_start)) ?>">
                    </div>
                    <div class="form-group">
                        <label>积分结束时间</label>
                        <input type="datetime-local" name="points_end" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($points_end)) ?>">
                    </div>
                </div>
                <button type="submit" name="update_home" class="btn-primary">保存设置</button>
            </form>
        </div>

        <!-- 轮播图管理 -->
        <div class="card-box">
            <h3>班级风采图片</h3>
            <form method="post" enctype="multipart/form-data">
                <div class="upload-row">
                    <div class="form-group" style="min-width: 200px; flex:2;">
                        <label>选择图片</label>
                        <input type="file" name="slide_image" class="form-control" required>
                    </div>
                    <div class="form-group" style="min-width: 80px; flex:1;">
                        <label>排序数字</label>
                        <input type="number" name="sort_order" class="form-control" value="0" placeholder="0">
                    </div>
                    <div style="padding-bottom: 1px;">
                        <button type="submit" class="btn-primary">上传图片</button>
                    </div>
                </div>
            </form>

            <div class="image-grid">
                <?php foreach ($slides as $s): ?>
                <div class="image-card">
                    <img src="../uploads/slides/<?= htmlspecialchars($s['image_url']) ?>" alt="轮播图">
                    <a href="?delete_slide=<?= $s['id'] ?>" class="btn-danger-outline" onclick="return confirm('确定要删除这张图片吗？');">删除</a>
                </div>
                <?php endforeach; ?>
                <?php if (empty($slides)): ?>
                    <div style="color:#9ca3af; font-size:14px; padding:10px 0;">暂无图片，请上传以展示班级风采。</div>
                <?php endif; ?>
            </div>
        </div>

        <a href="../index.php" target="_blank" class="btn-secondary">预览首页效果 ↗</a>
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