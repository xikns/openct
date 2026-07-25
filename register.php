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
        /* ===== 全局背景 ===== */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== 隐藏导航栏中的“登录”链接 ===== */
        .navbar a[href="login.php"] {
            display: none !important;
        }

        /* ===== 顶部标题区域 ===== */
        .register-header {
            text-align: center;
            padding: 40px 20px 20px;
        }
        .register-header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            word-break: break-word;
            margin-bottom: 0.3rem;
            letter-spacing: -0.5px;
        }
        .register-header p {
            color: #6b7280;
            font-size: 1.1rem;
            margin-top: 4px;
            font-weight: 400;
            letter-spacing: 1px;
        }

        /* ===== 卡片（毛玻璃效果） ===== */
        .card-apple {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 20px rgba(0, 0, 0, 0.02);
            transition: box-shadow 0.3s ease;
            overflow: hidden;
        }
        .card-apple:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.12);
        }

        .form-apple {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
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
            border-radius: 40px;
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

        /* ===== 底部版权 ===== */
        .register-footer {
            text-align: center;
            padding: 30px 20px 20px;
            color: #9ca3af;
            font-size: 0.9rem;
            line-height: 1.8;
        }
        .register-footer a {
            color: #4f46e5;
            text-decoration: none;
        }
        .register-footer a:hover {
            text-decoration: underline;
        }

        /* ===== 响应式适配 ===== */
        @media (max-width: 767.98px) {
            .register-header h1 {
                font-size: 2.2rem;
            }
            .register-header p {
                font-size: 0.95rem;
            }
            .card-apple {
                border-radius: 24px;
            }
            .card-apple .p-5 {
                padding: 2rem 1.5rem !important;
            }
            .form-apple {
                font-size: 15px;
                padding: 12px 16px;
                border-radius: 14px;
            }
            .btn-apple {
                font-size: 15px;
                padding: 12px 20px;
            }
            h2.fw-semibold {
                font-size: 1.5rem;
            }
            .text-accent a {
                font-size: 14px;
            }
            .container.py-4 {
                padding-top: 1.5rem !important;
                padding-bottom: 1rem !important;
            }
        }

        @media (max-width: 575.98px) {
            .register-header h1 {
                font-size: 1.8rem;
            }
            .register-header p {
                font-size: 0.85rem;
            }
            .card-apple .p-5 {
                padding: 1.5rem 1rem !important;
            }
            .form-apple {
                font-size: 14px;
                padding: 10px 14px;
                border-radius: 12px;
            }
            .btn-apple {
                font-size: 14px;
                padding: 10px 16px;
                border-radius: 30px;
            }
            h2.fw-semibold {
                font-size: 1.3rem;
            }
            .msg-box {
                font-size: 13px;
                padding: 8px 14px;
            }
            .register-footer {
                font-size: 0.8rem;
                padding: 20px 10px;
            }
        }
    </style>
</head>
<body>

    <!-- ===== 顶部标题区域 ===== -->
    <div class="register-header">
        <h1><?= htmlspecialchars($website_title) ?></h1>
        <p>积分管理系统 · 注册新账号</p>
    </div>

    <!-- ===== 注册卡片 ===== -->
    <div class="container py-4">
        <div class="row justify-content-center align-items-center" style="min-height: 60vh;">
            <div class="col-lg-6 col-md-8">
                <div class="card-apple p-5">
                    <h2 class="text-center fw-semibold mb-4" style="color: #1f2937;">📝 注册新账号</h2>

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

                    <!-- 底部返回登录链接 -->
                    <div class="text-center mt-4">
                        <a href="login.php" class="text-accent" style="font-size: 14px; color: #6b7280; text-decoration: none;">
                            已有账号？去登录 →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 底部版权信息 ===== -->
    <div class="register-footer">
        技术支持 © 2025-2026 <a href="http://xikexinxi.mysxl.cn" target="_blank">汐科信息工作室</a>. All Rights Reserved.<br>
        Powered By <a href="http://xikn.rf.gd/" target="_blank">汐科的博客</a>.
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