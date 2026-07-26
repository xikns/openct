<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
$class_name = getConfig('class_name') ?: '班级积分';
$website_title = getConfig('website_title') ?: '班级积分管理系统';
$currentUser = isLoggedIn() ? currentUser() : null;

// ★ 需要显示顶部导航栏的页面列表（文件名，不含路径）
$showNavPages = ['index.php', 'rankings.php',];
// 如果你还想让注册页、忘记密码页显示，可以添加：'register.php', 'forgot_password.php'
$currentPage = basename($_SERVER['PHP_SELF']);
$shouldShowNav = in_array($currentPage, $showNavPages);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($website_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="frontend-body">

<?php if ($shouldShowNav): ?>
<header class="nav-apple">
  <div class="container">
    <a href="index.php" class="fw-semibold text-dark text-decoration-none fs-5"><?= htmlspecialchars($class_name) ?></a>
    <div class="d-flex align-items-center gap-2">
      <?php if (isLoggedIn()): ?>
        <span class="text-secondary d-none d-sm-inline"><?= htmlspecialchars($currentUser['realname']) ?></span>
        <a href="profile.php" class="btn-apple sm secondary">个人中心</a>
        <?php if ($currentUser['role'] === 'teacher'): ?>
          <a href="admin/index.php" class="btn-apple sm outline">后台</a>
        <?php elseif ($currentUser['role'] === 'student_admin'): ?>
          <a href="admin/points.php" class="btn-apple sm outline">积分管理</a>
        <?php endif; ?>
        <a href="admin/logout.php" class="btn-apple sm secondary">退出</a>
      <?php else: ?>
        <a href="login.php" class="btn-apple sm primary">登录</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php endif; ?>