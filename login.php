<?php
require_once 'includes/header.php';

// 已登录用户跳转
if (isLoggedIn()) {
    $user = currentUser();
    if ($user['role'] === 'teacher') redirect('admin/index.php');
    elseif ($user['role'] === 'student_admin') redirect('admin/points.php');
    else redirect('profile.php');
}

$error = '';
$allow_register = getConfig('allow_register');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        if ($user['role'] === 'teacher') redirect('admin/index.php');
        elseif ($user['role'] === 'student_admin') redirect('admin/points.php');
        else redirect('profile.php');
    } else {
        $error = '账号或密码错误';
    }
}
?>

<div class="container d-flex align-items-center justify-content-center" style="min-height: 80vh;">
  <div class="card-apple glass p-5" style="max-width: 420px; width: 100%;">
    <h2 class="text-center fw-semibold mb-4">登录</h2>
    <?php if ($error): ?><div class="alert alert-danger rounded-pill"><?= $error ?></div><?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <input type="text" name="username" class="form-apple w-100" placeholder="学号 / 用户名" required>
      </div>
      <div class="mb-3">
        <input type="password" name="password" class="form-apple w-100" placeholder="密码" required>
      </div>
      <button type="submit" class="btn-apple primary w-100 mb-3">登录</button>
    </form>
    <p class="text-center text-accent mb-1">
      <a href="forgot_password.php">忘记密码？</a>
    </p>
    <?php if ($allow_register == '1'): ?>
      <p class="text-center text-accent mb-0">
        没有账号？ <a href="register.php">注册一个</a>
      </p>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>