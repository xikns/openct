<?php
require_once 'includes/header.php';
if (getConfig('allow_register') != '1') {
    die('注册功能已关闭');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $realname = trim($_POST['realname'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    if (empty($username) || empty($realname) || empty($password)) $error = '请填写必填项';
    elseif (strlen($password) < 6) $error = '密码至少6位';
    else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = '该学号已注册';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, realname, password, email, role, points) VALUES (?, ?, ?, ?, 'student', 0)");
            $stmt->execute([$username, $realname, $hashed, $email ?: null]);
            redirect('login.php?registered=1');
        }
    }
}
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card-custom">
                <h2 class="text-center">📝 注册新账号</h2>
                <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
                <form method="post">
                    <div class="mb-3"><label>学号</label><input type="text" name="username" class="form-control" required></div>
                    <div class="mb-3"><label>姓名</label><input type="text" name="realname" class="form-control" required></div>
                    <div class="mb-3"><label>邮箱（用于找回密码）</label><input type="email" name="email" class="form-control"></div>
                    <div class="mb-3"><label>密码</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                    <button type="submit" class="btn btn-fun w-100">注册 ✨</button>
                </form>
                <div class="text-center mt-3"><a href="login.php">已有账号？去登录</a></div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>