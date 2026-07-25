<?php
require_once 'includes/header.php';

if (getConfig('allow_register') != '1') {
    die('注册功能已关闭');
}

$error = '';
$website_title = getConfig('website_title') ?: '班级积分管理系统';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $realname = trim($_POST['realname'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($realname) || empty($password)) {
        $error = '请填写必填项';
    } elseif (strlen($password) < 6) {
        $error = '密码至少6位';
    } else {
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

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($website_title) ?> · 注册</title>
    <link rel="stylesheet" href="static/css/quick-website.css" id="stylesheet">
    <style>
        /* ===== 全局背景纯白 ===== */
        body {
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        /* ===== 隐藏导航栏中的“登录”链接 ===== */
        .navbar a[href="login.php"] {
            display: none !important;
        }

        /* ===== 主容器：左右独立，无外框 ===== */
        .register-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .register-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }
        .register-row {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 40px;
        }

        /* ===== 左侧图片（纯图片，无背景、无文字） ===== */
        .register-image {
            flex: 1 1 45%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: transparent;
        }
        .register-image img {
            max-width: 80%;
            height: auto;
            display: block;
        }

        /* ===== 右侧卡片（独立卡片） ===== */
        .register-form {
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
        .register-form:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.12);
        }
        .register-form .form-wrapper {
            width: 100%;
            max-width: 380px;
        }
        .register-form h2 {
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

        /* ===== 消息提示 ===== */
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

        /* ===== 响应式 ===== */
        @media (max-width: 767.98px) {
            .register-row {
                flex-direction: column;
                gap: 20px;
            }
            .register-image {
                flex: 1 1 auto;
                padding: 10px;
            }
            .register-image img {
                max-width: 60%;
            }
            .register-form {
                flex: 1 1 auto;
                padding: 30px 20px;
            }
            .register-form h2 {
                font-size: 1.6rem;
            }
        }

        @media (max-width: 575.98px) {
            .register-image img {
                max-width: 50%;
            }
            .register-form {
                padding: 20px 15px;
            }
            .register-form h2 {
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

    <!-- ===== 顶部标题已移除 ===== -->

    <!-- ===== 主区域：左图右表 ===== -->
    <div class="register-main">
        <div class="register-container">
            <div class="register-row">
                <!-- 左侧：仅图片 -->
                <div class="register-image">
                    <img src="/static/picture/TopImage.png" alt="班级积分">
                </div>
                <!-- 右侧：独立卡片 -->
                <div class="register-form">
                    <div class="form-wrapper">
                        <h2>📝 注册新账号</h2>

                        <?php if ($error): ?>
                            <div class="msg-box msg-error"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="mb-3">
                                <input type="text" name="username" class="form-apple" placeholder="学号" required>
                            </div>
                            <div class="mb-3">
                                <input type="text" name="realname" class="form-apple" placeholder="姓名" required>
                            </div>
                            <div class="mb-3">
                                <input type="email" name="email" class="form-apple" placeholder="邮箱（用于找回密码）">
                            </div>
                            <div class="mb-3">
                                <input type="password" name="password" class="form-apple" placeholder="密码（至少6位）" required minlength="6">
                            </div>
                            <button type="submit" class="btn-apple primary">注册 ✨</button>
                        </form>

                        <!-- 返回登录 -->
                        <div class="text-center mt-4">
                            <a href="login.php" class="text-accent" style="font-size: 14px; color: #6b7280; text-decoration: none;">
                                已有账号？去登录 →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 底部版权已移除 ===== -->

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