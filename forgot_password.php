<?php
require_once 'includes/header.php';
$step = isset($_GET['step']) ? $_GET['step'] : 'check'; // check / reset
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'check') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND email = ?");
    $stmt->execute([$username, $email]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        redirect('forgot_password.php?step=reset');
    } else {
        $error = '账号与邮箱不匹配';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset' && isset($_SESSION['reset_user_id'])) {
    $newpass = $_POST['new_password'] ?? '';
    if (strlen($newpass) < 6) $error = '密码至少6位';
    else {
        $hashed = password_hash($newpass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['reset_user_id']]);
        unset($_SESSION['reset_user_id']);
        $success = '密码重置成功，<a href="login.php">去登录</a>';
    }
}
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card-custom">
                <h2 class="text-center">🔑 忘记密码</h2>
                <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

                <?php if ($step === 'check'): ?>
                    <form method="post">
                        <div class="mb-3"><label>学号</label><input type="text" name="username" class="form-control" required></div>
                        <div class="mb-3"><label>注册邮箱</label><input type="email" name="email" class="form-control" required></div>
                        <button type="submit" class="btn btn-fun w-100">验证身份</button>
                    </form>
                <?php elseif ($step === 'reset' && isset($_SESSION['reset_user_id'])): ?>
                    <form method="post">
                        <div class="mb-3"><label>新密码</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                        <button type="submit" class="btn btn-fun w-100">重置密码</button>
                    </form>
                <?php endif; ?>
                <div class="text-center mt-3"><a href="login.php">← 返回登录</a></div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>