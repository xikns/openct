<?php
require_once 'includes/header.php';

if (isLoggedIn()) {
    $user = currentUser();
    if ($user['role'] === 'teacher') {
        redirect('admin/index.php');
    } elseif ($user['role'] === 'student_admin') {
        redirect('profile.php');
    } else {
        redirect('profile.php');
    }
}

$error = '';
$allow_register = getConfig('allow_register');
$website_title = getConfig('website_title') ?: '班级积分管理系统';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        if ($user['role'] === 'teacher') redirect('admin/index.php');
        elseif ($user['role'] === 'student_admin') redirect('profile.php');
        else redirect('profile.php');
    } else {
        $error = '账号或密码错误';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($website_title) ?> · 登录</title>
    <link rel="stylesheet" href="static/css/quick-website.css" id="stylesheet">
    <style>
        /* ===== 全局重置：背景纯白 ===== */
        body {
            background: #ffffff; /* 纯白背景 */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        /* ===== 主容器：左右独立，无外框 ===== */
        .login-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .login-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }
        .login-row {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 40px; /* 左右间距 */
        }

        /* ===== 左侧图片（纯图片，无背景、无文字） ===== */
        .login-image {
            flex: 1 1 45%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: transparent; /* 无背景色 */
        }
        .login-image img {
            max-width: 80%;
            height: auto;
            display: block;
        }

        /* ===== 右侧登录卡片（独立卡片） ===== */
        .login-form {
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
        .login-form:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.12);
        }
        .login-form .form-wrapper {
            width: 100%;
            max-width: 380px;
        }
        .login-form h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1f2937;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        /* ===== 表单控件 ===== */
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

        .alert.rounded-pill {
            border-radius: 50px !important;
            font-size: 14px;
            padding: 10px 20px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        /* ===== 响应式 ===== */
        @media (max-width: 767.98px) {
            .login-row {
                flex-direction: column;
                gap: 20px;
            }
            .login-image {
                flex: 1 1 auto;
                padding: 10px;
            }
            .login-image img {
                max-width: 60%;
            }
            .login-form {
                flex: 1 1 auto;
                padding: 30px 20px;
            }
        }

        @media (max-width: 575.98px) {
            .login-image img {
                max-width: 50%;
            }
            .login-form {
                padding: 20px 15px;
            }
            .login-form h2 {
                font-size: 1.5rem;
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
        }
    </style>
</head>
<body>

    <!-- ===== 顶部标题已移除 ===== -->

    <!-- ===== 主登录区域：左图右表，完全独立 ===== -->
    <div class="login-main">
        <div class="login-container">
            <div class="login-row">
                <!-- 左侧：仅图片 -->
                <div class="login-image">
                    <img src="/static/picture/TopImage.png" alt="班级积分">
                </div>
                <!-- 右侧：独立登录卡片 -->
                <div class="login-form">
                    <div class="form-wrapper">
                        <h2>欢迎回来</h2>
                        <?php if ($error): ?>
                            <div class="alert alert-danger rounded-pill"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <input type="text" name="username" class="form-apple" placeholder="学号 / 用户名" required>
                            </div>
                            <div class="mb-3">
                                <input type="password" name="password" class="form-apple" placeholder="密码" required>
                            </div>
                            <button type="submit" class="btn-apple primary mb-3">登录</button>
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
            </div>
        </div>
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

    <?php include 'includes/footer.php'; ?>
</body>
</html>