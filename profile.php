<?php
require_once 'includes/header.php';
requireLogin();
$user = currentUser();
if ($user['role'] === 'teacher') redirect('admin/index.php');
if ($user['role'] === 'student_admin') redirect('admin/points.php');

$message = '';

// 修改密码
if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    if (!password_verify($old, $user['password'])) $message = '原密码错误';
    elseif (strlen($new) < 6) $message = '新密码至少6位';
    else {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        $message = '密码修改成功';
    }
}

// 上传头像
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','gif'];
    if (!in_array(strtolower($ext), $allowed)) $message = '不允许的文件类型';
    else {
        $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], 'uploads/avatars/' . $filename);
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$filename, $user['id']]);
        $message = '头像更新成功';
        // 刷新用户数据
        $user = currentUser();
    }
}

// 积分变动记录
$logs = $pdo->prepare("SELECT pl.changed_points, pl.reason, pl.created_at, op.realname as operator_name FROM points_log pl LEFT JOIN users op ON pl.operator_id = op.id WHERE pl.user_id = ? ORDER BY pl.created_at DESC LIMIT 20");
$logs->execute([$user['id']]);
$logs = $logs->fetchAll();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card-custom">
                <h2>👤 <?= htmlspecialchars($user['realname']) ?> 的个人中心</h2>
                <?php if ($message): ?><div class="alert alert-info"><?= $message ?></div><?php endif; ?>
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img src="<?= $user['avatar'] ? 'uploads/avatars/'.$user['avatar'] : 'assets/default-avatar.png' ?>" class="avatar-circle mb-2" style="width:120px;height:120px;">
                        <form method="post" enctype="multipart/form-data">
                            <input type="file" name="avatar" class="form-control mb-2">
                            <button type="submit" class="btn btn-sm btn-fun">上传头像</button>
                        </form>
                        <p class="mt-2">当前积分：<strong class="fs-3"><?= $user['points'] ?></strong> ✨</p>
                    </div>
                    <div class="col-md-8">
                        <h5>修改密码</h5>
                        <form method="post">
                            <div class="mb-2"><input type="password" name="old_password" class="form-control" placeholder="原密码" required></div>
                            <div class="mb-2"><input type="password" name="new_password" class="form-control" placeholder="新密码" required minlength="6"></div>
                            <button type="submit" name="change_password" class="btn btn-fun">修改密码</button>
                        </form>
                    </div>
                </div>
                <hr>
                <h5>📋 积分变动记录</h5>
                <table class="table table-sm">
                    <thead><tr><th>变动分值</th><th>原因</th><th>操作人</th><th>时间</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><?= $l['changed_points'] > 0 ? '+' . $l['changed_points'] : $l['changed_points'] ?></td>
                            <td><?= htmlspecialchars($l['reason'] ?? '') ?></td>
                            <td><?= htmlspecialchars($l['operator_name']) ?></td>
                            <td><?= $l['created_at'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>