<?php
require_once 'includes/header.php';

$step = $_GET['step'] ?? 'check';
$error = $success = '';
$website_title = getConfig('website_title') ?: '班级积分管理系统';

// 第一步：输入学号/邮箱，查找用户
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'check') {
    $identifier = trim($_POST['identifier'] ?? '');
    $stmt = $pdo->prepare("SELECT id, email, secret_question FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        // 根据用户设置选择验证路径
        if (!empty($user['secret_question'])) {
            redirect('forgot_password.php?step=choose_method');
        } elseif (!empty($user['email'])) {
            redirect('forgot_password.php?step=email_verify');
        } else {
            $error = '该账号未设置邮箱或保密问题，无法找回密码。请联系教师重置。';
            // 清除可能遗留的 session
            unset($_SESSION['reset_user_id']);
        }
    } else {
        $error = '账号或邮箱不存在';
    }
}

// 选择验证方式页面（无表单提交，直接跳转）
if ($step === 'choose_method' && isset($_SESSION['reset_user_id'])) {
    // 重新获取用户信息，以判断按钮可用性
    $stmt = $pdo->prepare("SELECT email, secret_question FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['reset_user_id']]);
    $userInfo = $stmt->fetch();
    if (!$userInfo) {
        $error = '用户不存在';
        unset($_SESSION['reset_user_id']);
        $step = 'check';
    }
}

// 邮箱验证页面
if ($step === 'email_verify' && isset($_SESSION['reset_user_id'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['reset_user_id']]);
    $email = $stmt->fetchColumn();
    if (empty($email)) {
        $error = '该账号未绑定邮箱。';
        $step = 'check';
        unset($_SESSION['reset_user_id']);
    }
}

// 处理邮箱验证提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'email_verify') {
    $inputEmail = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND email = ?");
    $stmt->execute([$_SESSION['reset_user_id'], $inputEmail]);
    if ($stmt->fetch()) {
        // 验证通过，跳转重置密码
        redirect('forgot_password.php?step=reset');
    } else {
        $error = '邮箱不匹配';
    }
}

// 保密问题验证页面
if ($step === 'secret_question' && isset($_SESSION['reset_user_id'])) {
    $stmt = $pdo->prepare("SELECT secret_question FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['reset_user_id']]);
    $question = $stmt->fetchColumn();
    if (empty($question)) {
        $error = '未设置保密问题。';
        $step = 'check';
        unset($_SESSION['reset_user_id']);
    }
}

// 处理保密问题答案提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'secret_question') {
    $answer = trim($_POST['secret_answer'] ?? '');
    $stmt = $pdo->prepare("SELECT secret_answer_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['reset_user_id']]);
    $hash = $stmt->fetchColumn();
    if ($hash && password_verify($answer, $hash)) {
        // 验证通过，跳转重置密码
        redirect('forgot_password.php?step=reset');
    } else {
        $error = '答案错误';
    }
}

// 重置密码步骤（通用，必须已有已验证的 session）
if ($step === 'reset' && !isset($_SESSION['reset_user_id'])) {
    redirect('forgot_password.php?step=check');
}

// 处理重置密码提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset' && isset($_SESSION['reset_user_id'])) {
    $newpass = $_POST['new_password'] ?? '';
    if (strlen($newpass) < 6) {
        $error = '密码至少6位';
    } else {
        $hashed = password_hash($newpass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['reset_user_id']]);
        unset($_SESSION['reset_user_id']);
        $success = '密码重置成功，<a href="login.php" style="color: #4f46e5; text-decoration: underline;">去登录</a>';
        $step = 'check'; // 显示成功信息后不展示表单
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($website_title) ?> · 忘记密码</title>
    <link rel="stylesheet" href="static/css/quick-website.css" id="stylesheet">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }
        .navbar a[href="login.php"] {
            display: none !important;
        }
        .forgot-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .forgot-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }
        .forgot-row {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 40px;
        }
        .forgot-image {
            flex: 1 1 45%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: transparent;
        }
        .forgot-image img {
            max-width: 80%;
            height: auto;
            display: block;
        }
        .forgot-form {
            flex: 1 1 45%;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            padding: 40px 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: box-shadow 0.3s ease;
        }
        .forgot-form:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.12);
        }
        .forgot-form .form-wrapper {
            width: 100%;
            max-width: 380px;
        }
        .forgot-form h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1f2937;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .form-apple {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
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
            border-radius: 28px;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.25s ease;
            cursor: pointer;
            box-sizing: border-box;
            width: 100%;
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
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.25);
        }
        .btn-apple.outline {
            background: transparent;
            border: 2px solid #4f46e5;
            color: #4f46e5;
        }
        .btn-apple.outline:hover {
            background: rgba(79, 70, 229, 0.05);
        }
        .btn-apple:disabled {
            background: #d1d5db;
            color: #9ca3af;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }
        .text-accent a {
            color: #6b7280;
            text-decoration: none;
            transition: color 0.2s;
            font-weight: 500;
        }
        .text-accent a:hover {
            color: #4f46e5;
            text-decoration: underline;
        }
        .msg-box {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid transparent;
        }
        .msg-error {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .msg-success {
            background: #f0fdf4;
            color: #15803d;
            border-color: #bbf7d0;
        }
        .msg-success a {
            color: #4f46e5;
            text-decoration: underline;
        }
        .btn-group-vertical {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        .question-text {
            background: #f9fafb;
            padding: 12px;
            border-radius: 10px;
            font-size: 15px;
            color: #374151;
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
        }

        @media (max-width: 767.98px) {
            .forgot-row {
                flex-direction: column;
                gap: 20px;
            }
            .forgot-image {
                flex: 1 1 auto;
                padding: 10px;
            }
            .forgot-image img {
                max-width: 60%;
            }
            .forgot-form {
                flex: 1 1 auto;
                padding: 30px 20px;
            }
            .forgot-form h2 {
                font-size: 1.6rem;
            }
        }
        @media (max-width: 575.98px) {
            .forgot-image img {
                max-width: 50%;
            }
            .forgot-form {
                padding: 20px 15px;
            }
            .forgot-form h2 {
                font-size: 1.4rem;
            }
            .form-apple {
                font-size: 14px;
                padding: 12px 16px;
                border-radius: 10px;
            }
            .btn-apple {
                font-size: 14px;
                padding: 12px 20px;
                border-radius: 24px;
            }
            .msg-box {
                font-size: 13px;
                padding: 8px 14px;
            }
        }
    </style>
</head>
<body>

<div class="forgot-main">
    <div class="forgot-container">
        <div class="forgot-row">
            <div class="forgot-image">
                <img src="/static/picture/TopImage.png" alt="班级积分">
            </div>
            <div class="forgot-form">
                <div class="form-wrapper">
                    <h2>🔑 忘记密码</h2>

                    <?php if ($error): ?>
                        <div class="msg-box msg-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="msg-box msg-success"><?= $success ?></div>
                    <?php endif; ?>

                    <?php if ($step === 'check'): ?>
                        <form method="post">
                            <div class="mb-3">
                                <input type="text" name="identifier" class="form-apple" placeholder="学号或注册邮箱" required>
                            </div>
                            <button type="submit" class="btn-apple primary">下一步</button>
                        </form>

                    <?php elseif ($step === 'choose_method' && isset($_SESSION['reset_user_id'])): ?>
                        <div class="btn-group-vertical">
                            <?php if (!empty($userInfo['email'])): ?>
                                <a href="forgot_password.php?step=email_verify" class="btn-apple primary">📧 通过邮箱验证</a>
                            <?php else: ?>
                                <button class="btn-apple primary" disabled>📧 邮箱未绑定</button>
                            <?php endif; ?>

                            <?php if (!empty($userInfo['secret_question'])): ?>
                                <a href="forgot_password.php?step=secret_question" class="btn-apple outline">🔐 通过保密问题验证</a>
                            <?php else: ?>
                                <button class="btn-apple outline" disabled>🔐 未设置保密问题</button>
                            <?php endif; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="forgot_password.php?step=check">← 重新输入账号</a>
                        </div>

                    <?php elseif ($step === 'email_verify' && isset($_SESSION['reset_user_id'])): ?>
                        <form method="post">
                            <div class="mb-3">
                                <label style="font-size:14px; color:#4b5563; margin-bottom:6px; display:block;">请输入绑定的邮箱</label>
                                <input type="email" name="email" class="form-apple" placeholder="example@qq.com" required>
                            </div>
                            <button type="submit" class="btn-apple primary">验证邮箱</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="forgot_password.php?step=choose_method">← 返回选择</a>
                        </div>

                    <?php elseif ($step === 'secret_question' && isset($_SESSION['reset_user_id'])): ?>
                        <div class="question-text">
                            🔐 保密问题：<strong><?= htmlspecialchars($question) ?></strong>
                        </div>
                        <form method="post">
                            <div class="mb-3">
                                <input type="text" name="secret_answer" class="form-apple" placeholder="请输入答案" required>
                            </div>
                            <button type="submit" class="btn-apple primary">验证答案</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="forgot_password.php?step=choose_method">← 返回选择</a>
                        </div>

                    <?php elseif ($step === 'reset' && isset($_SESSION['reset_user_id'])): ?>
                        <form method="post">
                            <div class="mb-3">
                                <input type="password" name="new_password" class="form-apple" placeholder="新密码（至少6位）" required minlength="6">
                            </div>
                            <button type="submit" class="btn-apple primary">重置密码</button>
                        </form>

                    <?php else: ?>
                        <!-- 意外情况，回到第一步 -->
                        <div class="text-center">
                            <a href="forgot_password.php?step=check">重新开始</a>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="login.php" class="text-accent" style="font-size: 14px; color: #6b7280; text-decoration: none;">
                            ← 返回登录
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 移除导航栏中的“登录”链接（兼容原模板）
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

<?php include 'includes/footer.php'; ?>
</body>
</html>