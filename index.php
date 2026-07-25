<?php
// 初始化系统（加载数据库连接和函数，不输出任何 HTML）
ob_start();
require_once 'includes/header.php';
ob_end_clean();

// ---- 获取数据 ----
$class_name = getConfig('class_name') ?: '阳光一班';
$stmt = $pdo->prepare("SELECT realname, points, avatar FROM users WHERE role IN ('student','student_admin') ORDER BY points DESC LIMIT 3");
$stmt->execute();
$top3 = $stmt->fetchAll();
$slides = $pdo->query("SELECT image_url FROM slides ORDER BY sort_order ASC")->fetchAll();

// 获取抽奖开关状态
$lottery_enabled = getConfig('lottery_enabled');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <title><?= htmlspecialchars($class_name) ?> · 积分管理系统</title>
    <meta name="description" content='<?= htmlspecialchars($class_name) ?>积分管理系统，高效管理班级积分。'>
    <meta name="keywords" content='积分管理,班级积分,排行榜'>
    
    <!-- 原有样式 + 全屏 + 动画增强 + 额外响应式 -->
    <style>
        /* ===== 预加载 ===== */
        @keyframes hidePreloader {
            0% { width: 100%; height: 100%; }
            100% { width: 0; height: 0; }
        }
        body>div.preloader {
            position: fixed;
            background: white;
            width: 100%;
            height: 100%;
            z-index: 1071;
            opacity: 0;
            transition: opacity .5s ease;
            overflow: hidden;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        body:not(.loaded)>div.preloader { opacity: 1; }
        body:not(.loaded) { overflow: hidden; }
        body.loaded>div.preloader { animation: hidePreloader .5s linear .5s forwards; }

        /* ===== 首屏全屏 ===== */
        .hero-fullscreen {
            min-height: calc(100vh - 76px);
            display: flex;
            align-items: center;
            padding-top: 0;
            padding-bottom: 0;
        }
        .hero-fullscreen .container,
        .hero-fullscreen .row {
            height: 100%;
        }
        @media (max-width: 991.98px) {
            .hero-fullscreen { min-height: calc(100vh - 60px); }
        }
        @media (max-width: 575.98px) {
            .hero-fullscreen { min-height: calc(100vh - 56px); }
        }

        /* ===== 入场动画 ===== */
        .animate-fade-up {
            opacity: 0;
            transform: translateY(30px);
        }
        body.loaded .animate-fade-up {
            animation: fadeInUp 0.8s ease forwards;
        }
        body.loaded .animate-fade-up.delay-1 {
            animation-delay: 0.2s;
        }
        body.loaded .animate-fade-up.delay-2 {
            animation-delay: 0.4s;
        }

        .rank-item {
            opacity: 0;
            transform: translateX(-30px);
        }
        body.loaded .rank-item {
            animation: slideInLeft 0.6s ease forwards;
        }
        body.loaded .rank-item:nth-child(1) { animation-delay: 0.1s; }
        body.loaded .rank-item:nth-child(2) { animation-delay: 0.25s; }
        body.loaded .rank-item:nth-child(3) { animation-delay: 0.4s; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* 卡片悬停微动 */
        .card.hover-shadow-lg {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card.hover-shadow-lg:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0,0,0,.15) !important;
        }

        /* ===== 排行榜样式（响应式增强） ===== */
        .rank-item {
            display: flex;
            align-items: center;
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid #e8e8ed;
            flex-wrap: wrap; /* 小屏换行 */
        }
        .rank-item:last-child { border-bottom: 0; }
        .rank-number {
            font-size: 2rem;
            font-weight: 600;
            width: 50px;
            color: #bdc3c7;
            flex-shrink: 0;
        }
        .rank-number.gold { color: #f1c40f; }
        .rank-number.silver { color: #bdc3c7; }
        .rank-number.bronze { color: #e67e22; }
        .rank-name {
            flex: 1;
            font-weight: 500;
            word-break: break-word;
            padding-right: 0.5rem;
        }
        .rank-points {
            font-weight: 600;
            color: #0071e3;
            white-space: nowrap;
        }

        /* 轮播图片自适应 */
        .carousel-inner img {
            aspect-ratio: 16/9;
            object-fit: cover;
            width: 100%;
            height: auto;
        }
        #ranking { scroll-margin-top: 80px; }

        /* ===== 响应式补充 ===== */
        @media (max-width: 767.98px) {
            .hero-fullscreen {
                min-height: auto;
                padding: 3rem 0;
            }
            .display-4 {
                font-size: 2.2rem;
            }
            .rank-number {
                font-size: 1.5rem;
                width: 40px;
            }
            .rank-item {
                padding: 0.5rem 0.3rem;
            }
            .rank-points {
                font-size: 0.9rem;
            }
            .carousel-inner img {
                aspect-ratio: 4/3;
            }
            /* 负边距卡片在小屏取消叠层 */
            .mt-n7,
            .mt-sm-n7,
            .mt-xl-n7 {
                margin-top: 0 !important;
            }
            .card.hover-shadow-lg {
                margin-bottom: 1rem;
            }
            .slice-lg {
                padding-top: 2rem;
                padding-bottom: 2rem;
            }
            .py-6 {
                padding-top: 2rem;
                padding-bottom: 2rem;
            }
            .btn-group-mobile {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                justify-content: center;
            }
            .btn-group-mobile .btn {
                flex: 1 0 auto;
                min-width: 120px;
            }
        }

        @media (max-width: 575.98px) {
            .display-4 {
                font-size: 1.8rem;
            }
            .rank-number {
                font-size: 1.2rem;
                width: 30px;
            }
            .rank-name {
                font-size: 0.95rem;
            }
            .rank-points {
                font-size: 0.85rem;
            }
            .navbar-brand img {
                max-height: 32px;
            }
            .hero-fullscreen .container {
                padding-left: 15px;
                padding-right: 15px;
            }
        }

        /* 导航栏 Logo 自适应 */
        #navbar-logo {
            max-height: 40px;
            width: auto;
        }
        @media (max-width: 575.98px) {
            #navbar-logo {
                max-height: 32px;
            }
        }
    </style>

    <script>
        window.addEventListener("load", function() {
            setTimeout(function() {
                document.querySelector('body').classList.add('loaded');
            }, 300);
        });
    </script>

    <link rel="stylesheet" href="static/css/quick-website.css" id="stylesheet">
</head>

<body>
    <!-- Preloader -->
    <div class="preloader">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <!-- ===== 导航栏 ===== -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand" href="#" title="积分系统">
                <img src="static/picture/ailogo.png" id="navbar-logo" alt="Logo">
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav mt-4 mt-lg-0 ml-auto">
                    <li class="nav-item active">
                        <a class="nav-link" href="#">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/rankings.php">排行榜</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://xikn.rf.gd" target="_blank">汐科博客</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://xikexinxi.msxl.cn" target="_blank">汐科信息工作室</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                           href="https://github.com/xikns/openct"
                           target="_blank"
                           style="
                                  color: #4F46E5;
                                  font-weight: 600;
                                  padding: 6px 16px;
                                  border-radius: 20px;
                                  background: rgba(79, 70, 229, 0.08);
                                  transition: all 0.3s ease;
                                  text-decoration: none;
                                  display: inline-block;
                                "
                           onmouseover="this.style.color='#ffffff'; this.style.background='#4F46E5'; this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 14px rgba(79,70,229,0.35)'"
                           onmouseout="this.style.color='#4F46E5'; this.style.background='rgba(79,70,229,0.08)'; this.style.transform='scale(1)'; this.style.boxShadow='none'"
                        >开源地址</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ===== 首屏 ===== -->
    <section class="slice hero-fullscreen">
        <div class="container">
            <div class="row row-grid align-items-center">
                <div class="col-12 col-md-5 col-lg-6 order-md-2 text-center">
                    <figure class="w-100">
                        <img alt="班级形象" src="static/picture/TopImage.png" class="img-fluid mw-md-100">
                    </figure>
                </div>
                <div class="col-12 col-md-7 col-lg-6 order-md-1 pr-md-5">
                    <h1 class="display-4 text-center text-md-left mb-3 animate-fade-up">
                        <strong class="text-primary"><?= htmlspecialchars($class_name) ?></strong>
                    </h1>
                    <p class="lead text-center text-md-left text-muted animate-fade-up delay-1">
                        积分管理系统 · 激励成长 · 数据透明
                    </p>
                    <div class="text-center text-md-left mt-5 animate-fade-up delay-2 btn-group-mobile">
                        <?php if (isLoggedIn()): ?>
                            <a href="profile.php" class="btn btn-primary btn-icon">
                                <span class="btn-inner--text">个人中心</span>
                                <span class="btn-inner--icon"><i data-feather="chevron-right"></i></span>
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-icon">
                                <span class="btn-inner--text">登录系统</span>
                                <span class="btn-inner--icon"><i data-feather="chevron-right"></i></span>
                            </a>
                        <?php endif; ?>

                        <?php if ($lottery_enabled == '1'): ?>
                            <a href="<?= isLoggedIn() ? 'lottery.php' : 'login.php' ?>" class="btn btn-primary btn-icon">
                                <span class="btn-inner--text"><?= isLoggedIn() ? '积分抽奖' : '登录后抽奖' ?></span>
                                <span class="btn-inner--icon"><i data-feather="chevron-right"></i></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== 积分排行榜 ===== -->
    <section class="slice slice-lg" id="ranking">
        <div class="container">
            <div class="py-6">
                <div class="row row-grid justify-content-between align-items-center">
                    <div class="col-lg-5 order-lg-2">
                        <h5 class="h3">积分排行榜</h5>
                        <p class="lead my-4">班级积分前三名</p>
                        <?php if ($top3): ?>
                            <?php foreach ($top3 as $idx => $s): ?>
                            <div class="rank-item">
                                <span class="rank-number <?= $idx==0?'gold':($idx==1?'silver':'bronze') ?>"><?= $idx+1 ?></span>
                                <span class="rank-name"><?= htmlspecialchars($s['realname']) ?></span>
                                <span class="rank-points"><?= $s['points'] ?> 分</span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">暂无学生数据</p>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="/rankings.php" class="btn btn-outline-primary btn-sm">查看全部</a>
                        </div>
                    </div>
                    <div class="col-lg-6 order-lg-1">
                        <div class="card mb-0 mr-lg-5">
                            <div class="card-body p-2">
                                <img alt="排行榜插图" src="static/picture/screen-1.jpg" class="img-fluid shadow rounded">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== 班级风采 ===== -->
            <div class="py-6">
                <div class="row row-grid justify-content-between align-items-center">
                    <div class="col-lg-5">
                        <h5 class="h3">📸 班级风采</h5>
                        <p class="lead my-4">记录我们的精彩瞬间</p>
                        <?php if ($slides): ?>
                            <div id="classCarousel" class="carousel slide" data-ride="carousel">
                                <div class="carousel-inner">
                                    <?php foreach ($slides as $i => $img): ?>
                                    <div class="carousel-item <?= $i===0 ? 'active' : '' ?>">
                                        <img src="uploads/slides/<?= htmlspecialchars($img['image_url']) ?>" class="d-block w-100" alt="班级风采">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <a class="carousel-control-prev" href="#classCarousel" role="button" data-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="sr-only">上一张</span>
                                </a>
                                <a class="carousel-control-next" href="#classCarousel" role="button" data-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="sr-only">下一张</span>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">班级风采即将上线</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-6">
                        <div class="card mb-0 ml-lg-5">
                            <div class="card-body p-2">
                                <img alt="风采插图" src="static/picture/screen-2.jpg" class="img-fluid shadow rounded">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== 底部功能区 ===== -->
    <section class="slice slice-lg bg-section-dark pt-5 pt-lg-8">
        <div class="shape-container shape-line shape-position-top shape-orientation-inverse">
            <svg width="2560px" height="100px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="none" x="0px" y="0px" viewBox="0 0 2560 100" style="enable-background:new 0 0 2560 100;" xml:space="preserve" class="">
                <polygon points="2560 0 2560 100 0 100"></polygon>
            </svg>
        </div>
        <div class="container position-relative zindex-100">
            <div class="col">
                <div class="row justify-content-center">
                    <div class="col-md-10 text-center">
                        <div class="mt-4 mb-6">
                            <h2 class="h1 text-white">班级积分管理系统平台OpenCT</h2>
                            <h4 class="text-white mt-3">建站   够快   够省   够简单</h4>
                            <a class="nav-link"
                               href="https://github.com/xikns/openct"
                               target="_blank"
                               style="
                                      color: #4F46E5;
                                      font-weight: 600;
                                      padding: 6px 16px;
                                      border-radius: 20px;
                                      background: rgba(79, 70, 229, 0.08);
                                      transition: all 0.3s ease;
                                      text-decoration: none;
                                      display: inline-block;
                                    "
                               onmouseover="this.style.color='#ffffff'; this.style.background='#4F46E5'; this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 14px rgba(79,70,229,0.35)'"
                               onmouseout="this.style.color='#4F46E5'; this.style.background='rgba(79,70,229,0.08)'; this.style.transform='scale(1)'; this.style.boxShadow='none'"
                            >开源地址</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="slice pt-0">
        <div class="container position-relative zindex-100">
            <div class="row">
                <div class="col-xl-4 col-sm-6 mt-n7">
                    <div class="card hover-shadow-lg">
                        <div class="d-flex p-5">
                            <div><span class="badge badge-warning badge-pill">开源</span></div>
                            <div class="pl-4">
                                <h5 class="lh-130">源码开源</h5>
                                <p class="text-muted mb-0">开源源码，维护高效且轻松</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-sm-6 mt-sm-n7">
                    <div class="card hover-shadow-lg">
                        <div class="d-flex p-5">
                            <div><span class="badge badge-success badge-pill">专业</span></div>
                            <div class="pl-4">
                                <h5 class="lh-130">数据直观</h5>
                                <p class="text-muted mb-0">数据直观表现，管理方便高效</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-12 col-sm-6 mt-xl-n7">
                    <div class="card hover-shadow-lg">
                        <div class="d-flex p-5">
                            <div><span class="badge badge-danger badge-pill">简洁</span></div>
                            <div class="pl-3">
                                <h5 class="lh-130">简洁设计</h5>
                                <p class="text-muted mb-0">简洁设计风格，符合企业级审美</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="slice slice-lg pt-lg-6 pb-0 pb-lg-6 bg-section-secondary">
        <div class="container">
            <div class="row mb-5 justify-content-center text-center">
                <div class="col-lg-6">
                    <h2 class="mt-4">功能介绍</h2>
                </div>
            </div>
            <div class="row mt-5 text-center">
                <div class="col-md-4">
                    <a class="card hover-shadow-lg" href="#" target="_blank" title="SEO智能排名">
                        <div class="card-body pb-5">
                            <div class="pt-4 pb-5">
                                <img src="static/picture/ai-plugin.png" class="img-fluid img-center" style="height:150px;" alt="Illustration">
                            </div>
                            <h5 class="h4 lh-130 mb-3">排名直观</h5>
                            <p class="text-muted mb-0">显示全部排名，可以筛选出高分和低分学生，加分方便，积分管理直白，安全，小白也能操作</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a class="card hover-shadow-lg" href="#" target="_blank" title="营销策略">
                        <div class="card-body pb-5">
                            <div class="pt-4 pb-5">
                                <img src="static/picture/ai-video.png" class="img-fluid img-center" style="height:150px;" alt="Illustration">
                            </div>
                            <h5 class="h4 lh-130 mb-3">高效管理</h5>
                            <p class="text-muted mb-0">管理员可以对网站进行标题设置，抽奖设置，用户添加与管理，班级风采展示等操作</p>
                        </div>
                    </a>
                </div>
                <div class="col-md-4">
                    <a class="card hover-shadow-lg" href="#" target="_blank" title="营销定位">
                        <div class="card-body pb-5">
                            <div class="pt-4 pb-5">
                                <img src="static/picture/scerpot.png" class="img-fluid img-center" style="height:150px;" alt="Illustration">
                            </div>
                            <h5 class="h4 lh-130 mb-3">轻量无广</h5>
                            <p class="text-muted mb-0">不会有任何广告，清爽界面，高效管理，开源代码，实时维护</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== 页脚 ===== -->
    <footer class="position-relative" id="footer-main">
        <div class="footer pt-lg-7 footer-dark bg-dark">
            <div class="shape-container shape-line shape-position-top shape-orientation-inverse">
                <svg width="2560px" height="100px" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="none" x="0px" y="0px" viewBox="0 0 2560 100" style="enable-background:new 0 0 2560 100;" xml:space="preserve" class="fill-section-secondary">
                    <polygon points="2560 0 2560 100 0 100"></polygon>
                </svg>
            </div>
            <div class="container pt-4">
                <hr class="divider divider-fade divider-dark my-4">
                <div class="row align-items-center justify-content-md-between pb-4">
                    <div class="col-md-6">
                        <div class="copyright text-sm font-weight-bold text-center text-md-left"></div>
                    </div>
                    <div class="col-md-6">
                        <ul class="nav justify-content-center justify-content-md-end mt-3 mt-md-0">
                            <p>技术支持&copy; 2025-2026 <a href="http://xikexinxi.mysxl.cn" target="_blank">汐科信息工作室</a>. All Rights Reserved. <br />Powered By <a href="http://xikn.rf.gd/" target="_blank">汐科的博客</a>.</p>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript 依赖 -->
    <script src="static/js/jquery.min.js"></script>
    <script src="static/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.28.0/feather.min.js"></script>
    <script>feather.replace();</script>
</body>
</html>