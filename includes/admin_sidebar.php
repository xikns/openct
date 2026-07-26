<aside class="sidebar-apple">
  <div class="brand"><?= htmlspecialchars(getConfig('website_title') ?: '班级积分') ?></div>
  <nav>
    <?php if ($isTeacher): ?>
      <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">仪表盘</a>
      <a href="points.php" class="<?= basename($_SERVER['PHP_SELF']) == 'points.php' ? 'active' : '' ?>">积分管理</a>
      <a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">用户管理</a>
      <a href="homepage.php" class="<?= basename($_SERVER['PHP_SELF']) == 'homepage.php' ? 'active' : '' ?>">首页编辑</a>
      <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">设置</a>
      <a href="logs.php" class="<?= basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : '' ?>">操作日志</a>
      <a href="lottery.php" class="<?= basename($_SERVER['PHP_SELF']) == 'lottery.php' ? 'active' : '' ?>">抽奖设置</a>
    <?php elseif ($isStudentAdmin): ?>
      <a href="points.php" class="<?= basename($_SERVER['PHP_SELF']) == 'points.php' ? 'active' : '' ?>">积分管理</a>
    <?php endif; ?>
    <hr class="text-white-50">
    <!-- 所有后台用户都显示个人中心入口 -->
    <a href="../profile.php" class="text-accent">个人中心</a>
    <a href="logout.php" class="text-accent">↩ 退出</a>
  </nav>
</aside>