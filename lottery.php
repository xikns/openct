<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// ========== AJAX 统一处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isLoggedIn()) {
        echo json_encode(['error' => '请先登录']);
        exit;
    }

    $currentUser = currentUser();
    $isTeacher = ($currentUser['role'] === 'teacher');

    // ---------- 积分抽奖 ----------
    if ($_POST['action'] === 'draw') {
        if ($isTeacher && !empty($_POST['target_user_id'])) {
            $targetId = (int)$_POST['target_user_id'];
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('student','student_admin')");
            $stmt->execute([$targetId]);
            $drawUser = $stmt->fetch();
            if (!$drawUser) { echo json_encode(['error'=>'学生无效']); exit; }
        } else {
            $drawUser = $currentUser;
        }

        $cost = (int)(getConfig('lottery_cost') ?: 10);
        if (getConfig('lottery_enabled') != '1') { echo json_encode(['error'=>'积分抽奖已关闭']); exit; }
        if ($drawUser['points'] < $cost) { echo json_encode(['error'=>'积分不足，需要'.$cost.'积分']); exit; }

        $prizes = $pdo->query("SELECT * FROM prizes WHERE drawn < total")->fetchAll();
        if (empty($prizes)) { echo json_encode(['error'=>'奖品已抽完']); exit; }

        $totalWeight = array_sum(array_column($prizes, 'probability'));
        $rand = mt_rand(1, max(1, $totalWeight));
        $current = 0; $win = null;
        foreach ($prizes as $p) { $current += $p['probability']; if ($rand <= $current) { $win = $p; break; } }
        if (!$win) $win = end($prizes);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?")->execute([$cost, $drawUser['id'], $cost]);
            $pdo->prepare("UPDATE prizes SET drawn = drawn + 1 WHERE id = ? AND drawn < total")->execute([$win['id']]);
            $pdo->prepare("INSERT INTO lottery_records (user_id, prize_id) VALUES (?, ?)")->execute([$drawUser['id'], $win['id']]);
            $pdo->commit();
            echo json_encode(['success'=>true, 'prize'=>$win['name'], 'new_points'=>$drawUser['points'] - $cost, 'cost'=>$cost, 'draw_user_id'=>$drawUser['id']]);
        } catch (Exception $e) { $pdo->rollBack(); echo json_encode(['error'=>'系统错误']); }
        exit;
    }

    // ---------- 惩罚抽奖 ----------
    if ($_POST['action'] === 'penalty_draw') {
        if (!$isTeacher) { echo json_encode(['error'=>'无权操作']); exit; }
        if (getConfig('penalty_enabled') != '1') { echo json_encode(['error'=>'惩罚抽奖已关闭']); exit; }

        $targetId = (int)($_POST['target_user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('student','student_admin')");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) { echo json_encode(['error'=>'学生无效']); exit; }

        $prizes = $pdo->query("SELECT * FROM penalty_prizes WHERE drawn < total")->fetchAll();
        if (empty($prizes)) { echo json_encode(['error'=>'惩罚奖品已抽完']); exit; }

        $totalWeight = array_sum(array_column($prizes, 'probability'));
        $rand = mt_rand(1, max(1, $totalWeight));
        $current = 0; $win = null;
        foreach ($prizes as $p) { $current += $p['probability']; if ($rand <= $current) { $win = $p; break; } }
        if (!$win) $win = end($prizes);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE penalty_prizes SET drawn = drawn + 1 WHERE id = ? AND drawn < total")->execute([$win['id']]);
            $pdo->prepare("INSERT INTO penalty_records (user_id, prize_id) VALUES (?, ?)")->execute([$targetUser['id'], $win['id']]);
            $pdo->commit();
            echo json_encode(['success'=>true, 'prize'=>$win['name'], 'target_name'=>$targetUser['realname']]);
        } catch (Exception $e) { $pdo->rollBack(); echo json_encode(['error'=>'系统错误']); }
        exit;
    }
}

// ========== 页面显示 ==========
require_once 'includes/header.php';
requireLogin();
$currentUser = currentUser();
$isTeacher = ($currentUser['role'] === 'teacher');

// 积分抽奖数据
$lotteryEnabled = getConfig('lottery_enabled') == '1';
$lotteryCost = (int)(getConfig('lottery_cost') ?: 10);
$drawUser = $currentUser;
$canDraw = $lotteryEnabled && ($drawUser['points'] >= $lotteryCost);
$prizes = $pdo->query("SELECT * FROM prizes WHERE drawn < total ORDER BY id ASC")->fetchAll();

// 惩罚抽奖数据（仅教师）
$penaltyEnabled = getConfig('penalty_enabled') == '1';
$penaltyCount = (int)(getConfig('penalty_count') ?: 3);
$penaltyStudents = [];
if ($isTeacher && $penaltyEnabled) {
    $penaltyStudents = $pdo->query("SELECT id, realname, points, avatar FROM users WHERE role IN ('student','student_admin') ORDER BY points ASC LIMIT {$penaltyCount}")->fetchAll();
}
$penaltyPrizes = $pdo->query("SELECT * FROM penalty_prizes WHERE drawn < total ORDER BY id ASC")->fetchAll();

// 积分排名 TOP5
$top5 = $pdo->query("SELECT realname, points FROM users WHERE role IN ('student','student_admin') ORDER BY points DESC LIMIT 5")->fetchAll();

// 获取所有学生列表（用于教师下拉框）
$allStudents = [];
if ($isTeacher) {
    $allStudents = $pdo->query("SELECT id, realname, points FROM users WHERE role IN ('student','student_admin') ORDER BY realname")->fetchAll();
}

// ---- 获取所有奖品库存（含已抽完的） ----
$allPrizes = $pdo->query("SELECT * FROM prizes ORDER BY id ASC")->fetchAll();
$allPenaltyPrizes = [];
if ($isTeacher) {
    $allPenaltyPrizes = $pdo->query("SELECT * FROM penalty_prizes ORDER BY id ASC")->fetchAll();
}
?>

<!--- 自定义优化样式（纯色风格，一页完整显示） -->
<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --card-bg: rgba(255,255,255,0.75);
    --shadow: 0 20px 60px rgba(0,0,0,0.08);
    --radius: 24px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
body { background: #f0f4ff; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
.container { max-width: 1200px; }
.card-apple {
    background: var(--card-bg);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.4);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 20px 20px;
    transition: var(--transition);
}
.card-apple:hover { box-shadow: 0 30px 80px rgba(0,0,0,0.12); }
.nav-pills .nav-link {
    border-radius: 40px;
    padding: 10px 28px;
    font-weight: 600;
    color: #4b5563;
    background: rgba(255,255,255,0.5);
    backdrop-filter: blur(4px);
    transition: var(--transition);
    border: 1px solid transparent;
}
.nav-pills .nav-link.active {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    border-color: transparent;
}
.nav-pills .nav-link:not(.active):hover {
    background: rgba(255,255,255,0.9);
    transform: translateY(-2px);
}
.form-apple {
    background: rgba(255,255,255,0.7);
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 16px;
    padding: 10px 14px;
    font-size: 0.95rem;
    transition: var(--transition);
    outline: none;
}
.form-apple:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102,126,234,0.15);
}
.btn-apple {
    border: none;
    padding: 10px 24px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: var(--transition);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-apple.primary {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.35);
}
.btn-apple.primary:hover:not(:disabled) {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5);
}
.btn-apple.primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
.btn-apple.secondary {
    background: rgba(255,255,255,0.6);
    backdrop-filter: blur(4px);
    color: #4b5563;
    border: 1px solid rgba(0,0,0,0.05);
}
.btn-apple.secondary:hover {
    background: white;
    transform: translateY(-2px);
}
.wheel-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
}
.wheel-wrapper canvas {
    display: block;
    width: 100%;
    height: auto;
    max-width: 500px;
    max-height: 500px;
    aspect-ratio: 1/1;
    border-radius: 50%;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    transition: var(--transition);
}
.spin-btn {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: var(--primary-gradient);
    border: 4px solid white;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 8px 24px rgba(102,126,234,0.5);
    transition: var(--transition);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    letter-spacing: 0.5px;
}
.spin-btn:hover:not(:disabled) {
    transform: scale(1.08);
    box-shadow: 0 12px 32px rgba(102,126,234,0.7);
}
.spin-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.pointer {
    position: absolute;
    top: -16px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    width: 0;
    height: 0;
    border-left: 18px solid transparent;
    border-right: 18px solid transparent;
    border-top: 28px solid #ef4444;
    filter: drop-shadow(0 4px 8px rgba(239,68,68,0.4));
}
.result-box {
    min-height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 600;
    background: rgba(255,255,255,0.4);
    border-radius: 40px;
    padding: 10px 20px;
    margin-top: 16px;
    transition: var(--transition);
}
.result-box .prize-icon { font-size: 1.8rem; margin-right: 12px; }
.rank-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.rank-list li {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    font-size: 0.9rem;
}
.rank-list li:last-child { border-bottom: none; }
.rank-badge {
    display: inline-block;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: var(--primary-gradient);
    color: white;
    text-align: center;
    line-height: 26px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-right: 10px;
}
/* 库存列表样式 */
.stock-list {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 0.85rem;
}
.stock-list li {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px dashed rgba(0,0,0,0.04);
}
.stock-list li:last-child { border-bottom: none; }
.stock-label { color: #4b5563; }
.stock-count {
    font-weight: 600;
    color: #059669;
}
.stock-count.empty { color: #dc2626; }
.stock-divider {
    border: 0;
    border-top: 1px solid rgba(0,0,0,0.06);
    margin: 12px 0;
}
@media (max-width: 992px) {
    .wheel-wrapper { max-width: 400px; }
    .spin-btn { width: 68px; height: 68px; font-size: 0.9rem; }
    .pointer { top: -14px; border-left-width: 16px; border-right-width: 16px; border-top-width: 24px; }
}
@media (max-width: 768px) {
    .container { padding: 0 12px; }
    .card-apple { padding: 16px 14px; }
    .wheel-wrapper { max-width: 320px; }
    .spin-btn { width: 60px; height: 60px; font-size: 0.8rem; border-width: 3px; }
    .pointer { top: -12px; border-left-width: 14px; border-right-width: 14px; border-top-width: 20px; }
    .result-box { font-size: 1rem; padding: 8px 16px; min-height: 40px; }
}
@media (max-width: 576px) {
    .wheel-wrapper { max-width: 260px; }
    .spin-btn { width: 52px; height: 52px; font-size: 0.7rem; border-width: 3px; }
    .pointer { top: -10px; border-left-width: 12px; border-right-width: 12px; border-top-width: 16px; }
}
</style>

<div class="container py-3">
  <!-- 标签切换 -->
  <ul class="nav nav-pills mb-3 justify-content-center" id="lotteryTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="points-tab" data-bs-toggle="pill" data-bs-target="#points-lottery" type="button">🎡 积分抽奖</button>
    </li>
    <?php if ($isTeacher && $penaltyEnabled): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="penalty-tab" data-bs-toggle="pill" data-bs-target="#penalty-lottery" type="button">😈 惩罚抽奖</button>
    </li>
    <?php endif; ?>
  </ul>

  <div class="tab-content">
    <!-- ========== 积分抽奖面板 ========== -->
    <div class="tab-pane fade show active" id="points-lottery">
      <div class="row g-3">
        <!-- 左侧：转盘 + 结果 -->
        <div class="col-lg-8">
          <div class="card-apple text-center">
            <h3 class="fw-semibold mb-2" style="font-size:1.4rem;">🎡 积分抽奖</h3>
            <!-- 积分显示 -->
            <p class="text-muted mb-3" style="font-size:0.95rem;">
              每次消耗 <strong class="text-primary"><?= $lotteryCost ?></strong> 积分 · 
              <?php if ($isTeacher): ?>
                <span id="studentPointsLabel">当前学生积分：</span>
                <strong id="studentPointsDisplay" class="text-success"><?= !empty($allStudents) ? $allStudents[0]['points'] : 0 ?></strong>
              <?php else: ?>
                当前积分：<strong id="pointsCurrent" class="text-success"><?= $drawUser['points'] ?></strong>
              <?php endif; ?>
            </p>
            <div class="wheel-wrapper">
              <canvas id="pointsWheel" width="500" height="500"></canvas>
              <div class="pointer"></div>
              <button id="pointsSpin" class="spin-btn position-absolute top-50 start-50 translate-middle">抽奖</button>
            </div>
            <div id="pointsResult" class="result-box">🎲 点击抽奖试试手气</div>
          </div>
        </div>

        <!-- 右侧：选择抽奖人 + 排行榜 + 积分库存 -->
        <div class="col-lg-4">
          <?php if ($isTeacher): ?>
          <div class="card-apple mb-3">
            <label class="form-label fw-semibold" style="font-size:0.9rem;">👤 选择抽奖学生：</label>
            <select id="pointsStudentSelect" class="form-apple w-100">
              <?php foreach ($allStudents as $stu): ?>
                <option value="<?= $stu['id'] ?>" data-points="<?= $stu['points'] ?>">
                  <?= htmlspecialchars($stu['realname']) ?>（<?= $stu['points'] ?>分）
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div class="card-apple">
            <h6 class="fw-semibold mb-2" style="font-size:1rem;">🏆 积分排名 TOP5</h6>
            <ul class="rank-list">
              <?php foreach ($top5 as $i => $s): ?>
                <li>
                  <span><span class="rank-badge"><?= $i+1 ?></span><?= htmlspecialchars($s['realname']) ?></span>
                  <span class="fw-bold"><?= $s['points'] ?></span>
                </li>
              <?php endforeach; ?>
            </ul>

            <hr class="stock-divider">
            <h6 class="fw-semibold mb-2" style="font-size:0.95rem;">📦 积分奖品库存</h6>
            <ul class="stock-list">
              <?php if (!empty($allPrizes)): ?>
                <?php foreach ($allPrizes as $p): ?>
                  <li>
                    <span class="stock-label"><?= htmlspecialchars($p['name']) ?></span>
                    <span class="stock-count <?= ($p['total'] - $p['drawn']) <= 0 ? 'empty' : '' ?>">
                      <?= max(0, $p['total'] - $p['drawn']) ?>/<?= $p['total'] ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li><span class="stock-label">暂无积分奖品</span></li>
              <?php endif; ?>
            </ul>
          </div>

          <a href="index.php" class="btn-apple secondary w-100 mt-3">← 返回首页</a>
        </div>
      </div>
    </div>

    <!-- ========== 惩罚抽奖面板（左右布局，与积分一致） ========== -->
    <?php if ($isTeacher && $penaltyEnabled): ?>
    <div class="tab-pane fade" id="penalty-lottery">
      <div class="row g-3">
        <!-- 左侧：转盘 + 结果 -->
        <div class="col-lg-8">
          <div class="card-apple text-center">
            <h3 class="fw-semibold mb-2" style="font-size:1.4rem;">😈 惩罚抽奖</h3>
            <p class="text-muted mb-3" style="font-size:0.95rem;">惩罚抽奖不消耗积分</p>
            <div class="wheel-wrapper">
              <canvas id="penaltyWheel" width="500" height="500"></canvas>
              <div class="pointer"></div>
              <button id="penaltySpin" class="spin-btn position-absolute top-50 start-50 translate-middle">惩罚</button>
            </div>
            <div id="penaltyResult" class="result-box">😈 点击惩罚</div>
          </div>
        </div>

        <!-- 右侧：选择受罚学生 + 惩罚库存 + 返回 -->
        <div class="col-lg-4">
          <div class="card-apple mb-3">
            <label class="form-label fw-semibold" style="font-size:0.9rem;">👤 选择受罚学生（积分最低<?= $penaltyCount ?>人）：</label>
            <select id="penaltyStudentSelect" class="form-apple w-100">
              <?php foreach ($penaltyStudents as $stu): ?>
                <option value="<?= $stu['id'] ?>"><?= htmlspecialchars($stu['realname']) ?>（<?= $stu['points'] ?>分）</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="card-apple">
            <h6 class="fw-semibold mb-2" style="font-size:0.95rem;">📦 惩罚奖品库存</h6>
            <ul class="stock-list">
              <?php if (!empty($allPenaltyPrizes)): ?>
                <?php foreach ($allPenaltyPrizes as $p): ?>
                  <li>
                    <span class="stock-label"><?= htmlspecialchars($p['name']) ?></span>
                    <span class="stock-count <?= ($p['total'] - $p['drawn']) <= 0 ? 'empty' : '' ?>">
                      <?= max(0, $p['total'] - $p['drawn']) ?>/<?= $p['total'] ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li><span class="stock-label">暂无惩罚奖品</span></li>
              <?php endif; ?>
            </ul>
          </div>

          <a href="index.php" class="btn-apple secondary w-100 mt-3">← 返回首页</a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- 转盘绘制与动画（纯色风格）+ 库存实时更新（动画结束后更新） -->
<script>
(function() {
    // ----- 工具函数：颜色生成（纯色，无渐变） -----
    const baseColors = ['#FF6B6B','#4ECDC4','#FFD93D','#6C5CE7','#A8E6CF','#FF8C42','#45B7D1','#F9CA24','#FF7979','#BADC58'];
    function getColor(index, total) {
        if (total <= baseColors.length) return baseColors[index % baseColors.length];
        const hue = (index * 360 / total) % 360;
        return `hsl(${hue}, 70%, 60%)`;
    }

    // ----- 转盘类（纯色绘制，无渐变） -----
    class Wheel {
        constructor(canvasId, prizes) {
            this.canvas = document.getElementById(canvasId);
            if (!this.canvas) return;
            this.ctx = this.canvas.getContext('2d');
            this.prizes = prizes;
            this.startAngle = 0;
            this.animating = false;
            this.draw();
        }

        draw(highlightIndex = -1) {
            const ctx = this.ctx;
            const w = this.canvas.width, h = this.canvas.height;
            const cx = w/2, cy = h/2, r = Math.min(w,h)/2 - 8;
            const total = this.prizes.length;
            if (total === 0) {
                ctx.clearRect(0,0,w,h);
                ctx.fillStyle = '#e5e7eb';
                ctx.font = '18px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('暂无奖品', cx, cy+6);
                return;
            }
            const slice = (2 * Math.PI) / total;
            ctx.clearRect(0,0,w,h);

            for (let i=0; i<total; i++) {
                const angle = this.startAngle + i*slice;
                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.arc(cx, cy, r, angle, angle+slice);
                ctx.closePath();
                const color = getColor(i, total);
                ctx.fillStyle = (highlightIndex === i) ? '#FFD700' : color;
                ctx.fill();
                ctx.strokeStyle = 'rgba(255,255,255,0.8)';
                ctx.lineWidth = 2;
                ctx.stroke();

                ctx.save();
                ctx.translate(cx, cy);
                ctx.rotate(angle + slice/2);
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 15px "Inter", sans-serif';
                ctx.shadowColor = 'rgba(0,0,0,0.3)';
                ctx.shadowBlur = 6;
                const text = this.prizes[i].name;
                const displayText = text.length > 10 ? text.substring(0,8)+'…' : text;
                ctx.fillText(displayText, r*0.65, 4);
                ctx.restore();
            }

            ctx.beginPath();
            ctx.arc(cx, cy, 20, 0, 2*Math.PI);
            ctx.fillStyle = 'white';
            ctx.shadowColor = 'rgba(0,0,0,0.15)';
            ctx.shadowBlur = 10;
            ctx.fill();
            ctx.shadowBlur = 0;
            ctx.strokeStyle = '#667eea';
            ctx.lineWidth = 3;
            ctx.stroke();
        }

        spinTo(prizeIndex, callback) {
            if (this.animating) return;
            if (this.prizes.length === 0) return;
            this.animating = true;
            const total = this.prizes.length;
            const slice = (2 * Math.PI) / total;
            const targetAngle = -Math.PI/2 - (prizeIndex + 0.5) * slice;
            const currentAngle = this.startAngle;
            let delta = targetAngle - currentAngle;
            const minRotations = 5;
            const fullRotations = 2 * Math.PI * minRotations;
            while (delta < fullRotations) delta += 2 * Math.PI;
            const finalAngle = currentAngle + delta;
            const duration = 4000;
            const startTime = performance.now();
            const startAngle = currentAngle;

            const animate = (now) => {
                const elapsed = now - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const ease = 1 - Math.pow(1 - progress, 3);
                this.startAngle = startAngle + delta * ease;
                this.draw();
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    this.startAngle = finalAngle;
                    this.draw(prizeIndex);
                    this.animating = false;
                    if (callback) callback();
                }
            };
            requestAnimationFrame(animate);
        }
    }

    // ----- 初始化转盘 -----
    const pointsPrizes = <?= json_encode(array_values($prizes)) ?>;
    const penaltyPrizes = <?= json_encode(array_values($penaltyPrizes)) ?>;
    const pointsWheel = new Wheel('pointsWheel', pointsPrizes);
    const penaltyWheel = new Wheel('penaltyWheel', penaltyPrizes);

    // ----- 积分显示元素 -----
    const isTeacher = <?= $isTeacher ? 'true' : 'false' ?>;
    const studentSelect = document.getElementById('pointsStudentSelect');
    const studentPointsDisplay = document.getElementById('studentPointsDisplay');
    const pointsCurrent = document.getElementById('pointsCurrent');

    if (isTeacher && studentSelect && studentPointsDisplay) {
        const firstOpt = studentSelect.options[0];
        if (firstOpt) {
            studentPointsDisplay.textContent = firstOpt.dataset.points || 0;
        }
        studentSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (opt) {
                studentPointsDisplay.textContent = opt.dataset.points || 0;
            }
        });
    }

    // ========== 库存实时更新函数（供动画回调调用） ==========
    function updateStock(type, prizeName) {
        const listSelector = type === 'points' ? '#points-lottery .stock-list' : '#penalty-lottery .stock-list';
        const list = document.querySelector(listSelector);
        if (!list) return;
        const items = list.querySelectorAll('li');
        for (let item of items) {
            const label = item.querySelector('.stock-label');
            if (label && label.textContent.trim() === prizeName) {
                const countSpan = item.querySelector('.stock-count');
                if (countSpan) {
                    const text = countSpan.textContent; // e.g., "5/10"
                    const parts = text.split('/');
                    if (parts.length === 2) {
                        let remaining = parseInt(parts[0]) - 1;
                        if (remaining < 0) remaining = 0;
                        const total = parseInt(parts[1]);
                        countSpan.textContent = remaining + '/' + total;
                        if (remaining === 0) {
                            countSpan.classList.add('empty');
                        } else {
                            countSpan.classList.remove('empty');
                        }
                    }
                }
                break;
            }
        }
    }

    // ----- 积分抽奖 -----
    const pointsSpinBtn = document.getElementById('pointsSpin');
    const pointsResult = document.getElementById('pointsResult');

    if (pointsSpinBtn) {
        pointsSpinBtn.addEventListener('click', function() {
            if (this.disabled || pointsWheel.animating) return;
            this.disabled = true;
            pointsResult.innerHTML = '⏳ 抽奖中...';
            let body = 'action=draw';
            if (isTeacher && studentSelect) {
                body += '&target_user_id=' + studentSelect.value;
            }
            fetch('lottery.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    pointsResult.innerHTML = `❌ ${data.error}`;
                    this.disabled = false;
                    return;
                }
                // 更新积分显示（在动画前更新）
                const newPoints = data.new_points;
                if (isTeacher) {
                    if (studentPointsDisplay) {
                        studentPointsDisplay.textContent = newPoints;
                    }
                    if (studentSelect) {
                        const selectedOpt = studentSelect.options[studentSelect.selectedIndex];
                        if (selectedOpt) {
                            selectedOpt.dataset.points = newPoints;
                            const name = selectedOpt.text.replace(/（\d+分）$/, '');
                            selectedOpt.text = name + '（' + newPoints + '分）';
                        }
                    }
                } else {
                    if (pointsCurrent) {
                        pointsCurrent.textContent = newPoints;
                    }
                }

                // 转盘动画
                const idx = pointsPrizes.findIndex(p => p.name === data.prize);
                if (idx === -1) {
                    pointsResult.innerHTML = `🎉 恭喜获得：${data.prize}！`;
                    this.disabled = false;
                    return;
                }
                // 动画结束后更新库存和结果
                pointsWheel.spinTo(idx, () => {
                    // 库存更新放在动画结束后
                    updateStock('points', data.prize);
                    pointsResult.innerHTML = `🎉 恭喜获得：<strong>${data.prize}</strong>！`;
                    const cost = <?= $lotteryCost ?>;
                    if (newPoints < cost) {
                        this.disabled = true;
                        pointsResult.innerHTML += ' 😅 积分不足，无法继续抽奖';
                    } else {
                        this.disabled = false;
                        this.textContent = '再抽一次';
                    }
                });
            })
            .catch(() => {
                pointsResult.innerHTML = '❌ 网络错误，请重试';
                this.disabled = false;
            });
        });
    }

    // ----- 惩罚抽奖 -----
    const penaltySpinBtn = document.getElementById('penaltySpin');
    const penaltyResult = document.getElementById('penaltyResult');
    if (penaltySpinBtn) {
        penaltySpinBtn.addEventListener('click', function() {
            if (this.disabled || penaltyWheel.animating) return;
            this.disabled = true;
            penaltyResult.innerHTML = '⏳ 抽奖中...';
            const targetId = document.getElementById('penaltyStudentSelect').value;
            fetch('lottery.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=penalty_draw&target_user_id=${targetId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    penaltyResult.innerHTML = `❌ ${data.error}`;
                    this.disabled = false;
                    return;
                }
                const idx = penaltyPrizes.findIndex(p => p.name === data.prize);
                if (idx === -1) {
                    penaltyResult.innerHTML = `😈 ${data.target_name} 获得了：${data.prize}`;
                    this.disabled = false;
                    return;
                }
                // 动画结束后更新库存和结果
                penaltyWheel.spinTo(idx, () => {
                    updateStock('penalty', data.prize);
                    penaltyResult.innerHTML = `😈 <strong>${data.target_name}</strong> 获得了：<strong>${data.prize}</strong>`;
                    this.disabled = false;
                    this.textContent = '再惩罚';
                });
            })
            .catch(() => {
                penaltyResult.innerHTML = '❌ 网络错误，请重试';
                this.disabled = false;
            });
        });
    }

})();
</script>

<?php include 'includes/footer.php'; ?>