<?php
require_once 'includes/header.php';

// ---- 查询首页所需数据 ----
$class_name = getConfig('class_name') ?: '阳光一班';
$start_time = getConfig('points_start');
$end_time   = getConfig('points_end');

// 前三名学生
$stmt = $pdo->prepare("SELECT realname, points, avatar FROM users WHERE role IN ('student','student_admin') ORDER BY points DESC LIMIT 3");
$stmt->execute();
$top3 = $stmt->fetchAll();

// 轮播图
$slides = $pdo->query("SELECT image_url FROM slides ORDER BY sort_order ASC")->fetchAll();

// 计算倒计时
$countdown = '';
if ($end_time) {
    $diff = strtotime($end_time) - time();
    if ($diff > 0) {
        $days  = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $countdown = "⏳ 距离截止 <strong>{$days}</strong> 天 <strong>{$hours}</strong> 小时";
    } else {
        $countdown = "🎉 本期积分已截止";
    }
}
?>

<main>
  <!-- Hero 区域（优化样式） -->
  <section class="hero" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 3rem 1rem; text-align: center; border-radius: 0 0 30px 30px; margin-bottom: 2rem;">
    <h1 style="font-size: 3rem; font-weight: 700; color: #2d3436; margin-bottom: 0.5rem;"><?= htmlspecialchars($class_name) ?></h1>
    <p style="font-size: 1.2rem; color: #636e72; margin: 0;">
      <?= $start_time ? date('Y.m.d', strtotime($start_time)) . ' 起' : '' ?>
      <?= $end_time ? ' · ' . $countdown : '' ?>
    </p>
  </section>

  <div class="container">
    <!-- 积分榜标题 -->
    <h2 class="text-center mb-4" style="font-weight: 600; color: #2d3436;">🏆 积分榜</h2>

    <!-- 前三名展示 -->
    <div class="row justify-content-center g-4 mb-5">
      <?php foreach ($top3 as $idx => $s): ?>
      <div class="col-md-4">
        <div class="card-apple text-center py-5" style="border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); transition: transform 0.2s ease; background: #fff;">
          <!-- 排名徽章 -->
          <div style="display: inline-block; background: <?= $idx==0?'#f1c40f':($idx==1?'#bdc3c7':'#e67e22') ?>; color: #fff; width: 40px; height: 40px; line-height: 40px; border-radius: 50%; font-weight: bold; font-size: 1.1rem; margin-bottom: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
            <?= $idx + 1 ?>
          </div>
          <img src="<?= $s['avatar'] ? 'uploads/avatars/'.$s['avatar'] : 'assets/default-avatar.png' ?>"
               class="avatar mx-auto my-3" style="width:80px;height:80px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
          <h3 class="fw-semibold mb-1" style="color: #2d3436;"><?= htmlspecialchars($s['realname']) ?></h3>
          <p class="text-accent mb-0" style="font-size: 1.6rem; font-weight: 700; color: #0984e3;"><?= $s['points'] ?> 积分</p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- 班级风采 -->
    <div style="background: #ffffff; border-radius: 20px; padding: 1.5rem 1.5rem 2rem; box-shadow: 0 5px 20px rgba(0,0,0,0.04); margin-bottom: 2rem;">
      <h2 class="text-center mb-3" style="font-weight: 600; color: #2d3436;">📸 班级风采</h2>
      <?php if ($slides): ?>
      <div id="classCarousel" class="carousel slide slideshow" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach ($slides as $i => $img): ?>
          <div class="carousel-item <?= $i===0 ? 'active' : '' ?>">
            <img src="uploads/slides/<?= htmlspecialchars($img['image_url']) ?>" class="d-block w-100" style="aspect-ratio: 16/9; object-fit: cover; border-radius: 12px;">
          </div>
          <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#classCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#classCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon"></span>
        </button>
      </div>
      <?php else: ?>
        <div class="card-apple text-center py-5 text-accent" style="border-radius: 16px; background: #f8f9fa;">班级照片即将上传</div>
      <?php endif; ?>
    </div>

    <!-- 按钮区域（登录与抽奖） -->
    <div class="text-center mt-5 mb-5">
      <?php if (!isLoggedIn()): ?>
        <a href="login.php" class="btn-apple primary btn-lg px-5">登录系统</a>
      <?php else: ?>
        <a href="profile.php" class="btn-apple primary btn-lg px-5">个人中心</a>
      <?php endif; ?>

      <?php if (lotteryEnabled()): ?>
        <?php if (isLoggedIn()): ?>
          <a href="lottery.php" class="btn-apple outline btn-lg px-5 ms-3">🎁 积分抽奖</a>
        <?php else: ?>
          <a href="login.php" class="btn-apple outline btn-lg px-5 ms-3">登录后抽奖</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include 'includes/footer.php'; ?>