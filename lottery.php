<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// ========== AJAX 统一处理（逻辑完全保留） ==========
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
?>

<!--- 自定义优化样式（同上一版） -->
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
    padding: 28px 24px;
    transition: var(--transition);
}
.card-apple:hover { box-shadow: 0 30px 80px rgba(0,0,0,0.12); }
.nav-pills .nav-link {
    border-radius: 40px;
    padding: 12px 32px;
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
    padding: 12px 16px;
    font-size: 1rem;
    transition: var(--transition);
    outline: none;
}
.form-apple:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102,126,234,0.15);
}
.btn-apple {
    border: none;
    padding: 12px 28px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 1rem;
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
}
.wheel-wrapper canvas {
    display: block;
    width: 100%;
    max-width: 400px;
    height: auto;
    aspect-ratio: 1/1;
    border-radius: 50%;
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    transition: var(--transition);
}
.spin-btn {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary-gradient);
    border: 4px solid white;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
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
    top: -18px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    width: 0;
    height: 0;
    border-left: 20px solid transparent;
    border-right: 20px solid transparent;
    border-top: 30px solid #ef4444;
    filter: drop-shadow(0 4px 8px rgba(239,68,68,0.4));
}
.result-box {
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 600;
    background: rgba(255,255,255,0.4);
    border-radius: 40px;
    padding: 12px 24px;
    margin-top: 20px;
    transition: var(--transition);
}
.result-box .prize-icon { font-size: 2rem; margin-right: 12px; }
.rank-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.rank-list li {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    font-size: 0.95rem;
}
.rank-list li:last-child { border-bottom: none; }
.rank-badge {
    display: inline-block;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--primary-gradient);
    color: white;
    text-align: center;
    line-height: 28px;
    font-size: 0.8rem;
    font-weight: 700;
    margin-right: 12px;
}
@media (max-width: 768px) {
    .container { padding: 0 16px; }
    .card-apple { padding: 20px 16px; }
    .spin-btn { width: 64px; height: 64px; font-size: 0.9rem; }
    .pointer { top: -12px; border-left-width: 14px; border-right-width: 14px; border-top-width: 22px; }
}
</style>

<div class="container py-4">
  <!-- 标签切换 -->
  <ul class="nav nav-pills mb-4 justify-content-center" id="lotteryTab" role="tablist">
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
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card-apple text-center">
            <h3 class="fw-semibold mb-3">🎡 积分抽奖</h3>
            <?php if ($isTeacher): ?>
            <div class="mb-4 text-start">
              <label class="form-label fw-semibold">👤 选择抽奖学生：</label>
              <select id="pointsStudentSelect" class="form-apple w-100">
                <?php foreach ($allStudents as $stu): ?>
                  <option value="<?= $stu['id'] ?>" data-points="<?= $stu['points'] ?>">
                    <?= htmlspecialchars($stu['realname']) ?>（<?= $stu['points'] ?>分）
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <!-- 积分显示区域：教师显示当前学生积分，学生显示自己的积分 -->
            <p class="text-muted mb-4">
              每次消耗 <strong class="text-primary"><?= $lotteryCost ?></strong> 积分 · 
              <?php if ($isTeacher): ?>
                <span id="studentPointsLabel">当前学生积分：</span>
                <strong id="studentPointsDisplay" class="text-success"><?= !empty($allStudents) ? $allStudents[0]['points'] : 0 ?></strong>
              <?php else: ?>
                当前积分：<strong id="pointsCurrent" class="text-success"><?= $drawUser['points'] ?></strong>
              <?php endif; ?>
            </p>
            <div class="wheel-wrapper">
              <canvas id="pointsWheel" width="400" height="400"></canvas>
              <div class="pointer"></div>
              <button id="pointsSpin" class="spin-btn position-absolute top-50 start-50 translate-middle">抽奖</button>
            </div>
            <div id="pointsResult" class="result-box">🎲 点击抽奖试试手气</div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card-apple">
            <h6 class="fw-semibold mb-3">🏆 积分排名 TOP5</h6>
            <ul class="rank-list">
              <?php foreach ($top5 as $i => $s): ?>
                <li>
                  <span><span class="rank-badge"><?= $i+1 ?></span><?= htmlspecialchars($s['realname']) ?></span>
                  <span class="fw-bold"><?= $s['points'] ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <a href="index.php" class="btn-apple secondary w-100 mt-3">← 返回首页</a>
        </div>
      </div>
    </div>

    <!-- ========== 惩罚抽奖面板（仅教师） ========== -->
    <?php if ($isTeacher && $penaltyEnabled): ?>
    <div class="tab-pane fade" id="penalty-lottery">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card-apple text-center">
            <h3 class="fw-semibold mb-3">😈 惩罚抽奖</h3>
            <div class="mb-4 text-start">
              <label class="form-label fw-semibold">👤 选择受罚学生（默认积分最低<?= $penaltyCount ?>人）：</label>
              <select id="penaltyStudentSelect" class="form-apple w-100">
                <?php foreach ($penaltyStudents as $stu): ?>
                  <option value="<?= $stu['id'] ?>"><?= htmlspecialchars($stu['realname']) ?>（<?= $stu['points'] ?>分）</option>
                <?php endforeach; ?>
              </select>
            </div>
            <p class="text-muted mb-4">惩罚抽奖不消耗积分</p>
            <div class="wheel-wrapper">
              <canvas id="penaltyWheel" width="400" height="400"></canvas>
              <div class="pointer"></div>
              <button id="penaltySpin" class="spin-btn position-absolute top-50 start-50 translate-middle">惩罚</button>
            </div>
            <div id="penaltyResult" class="result-box">😈 点击惩罚</div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- 转盘绘制与动画（优化版 + 积分实时更新修复） -->
<script>
(function() {
    // ----- 工具函数：颜色生成 -----
    const baseColors = ['#FF6B6B','#4ECDC4','#FFD93D','#6C5CE7','#A8E6CF','#FF8C42','#45B7D1','#F9CA24','#FF7979','#BADC58'];
    function getColor(index, total) {
        if (total <= baseColors.length) return baseColors[index % baseColors.length];
        const hue = (index * 360 / total) % 360;
        return `hsl(${hue}, 70%, 65%)`;
    }

    // ----- 转盘类 -----
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
            const cx = w/2, cy = h/2, r = Math.min(w,h)/2 - 10;
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

            const grad = ctx.createRadialGradient(cx, cy, r-10, cx, cy, r+10);
            grad.addColorStop(0, 'rgba(255,255,255,0)');
            grad.addColorStop(1, 'rgba(255,255,255,0.2)');
            ctx.beginPath();
            ctx.arc(cx, cy, r+10, 0, 2*Math.PI);
            ctx.fillStyle = grad;
            ctx.fill();

            for (let i=0; i<total; i++) {
                const angle = this.startAngle + i*slice;
                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.arc(cx, cy, r, angle, angle+slice);
                ctx.closePath();
                const grad2 = ctx.createRadialGradient(cx, cy, 20, cx, cy, r);
                const base = getColor(i, total);
                grad2.addColorStop(0, highlightIndex===i ? '#FFD700' : base);
                grad2.addColorStop(1, highlightIndex===i ? '#FFA500' : darken(base, 0.2));
                ctx.fillStyle = grad2;
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
                ctx.font = 'bold 14px "Inter", sans-serif';
                ctx.shadowColor = 'rgba(0,0,0,0.3)';
                ctx.shadowBlur = 6;
                const text = this.prizes[i].name;
                const displayText = text.length > 8 ? text.substring(0,6)+'…' : text;
                ctx.fillText(displayText, r*0.65, 4);
                ctx.restore();
            }

            ctx.beginPath();
            ctx.arc(cx, cy, 18, 0, 2*Math.PI);
            ctx.fillStyle = 'white';
            ctx.shadowColor = 'rgba(0,0,0,0.2)';
            ctx.shadowBlur = 12;
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

    function darken(hex, amount) {
        let r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
        r = Math.max(0, r - 50); g = Math.max(0, g - 50); b = Math.max(0, b - 50);
        return `#${r.toString(16).padStart(2,'0')}${g.toString(16).padStart(2,'0')}${b.toString(16).padStart(2,'0')}`;
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

    // 如果是教师模式，初始化显示第一个学生的积分，并监听下拉切换
    if (isTeacher && studentSelect && studentPointsDisplay) {
        // 初始显示
        const firstOpt = studentSelect.options[0];
        if (firstOpt) {
            studentPointsDisplay.textContent = firstOpt.dataset.points || 0;
        }
        // 切换时更新显示
        studentSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (opt) {
                studentPointsDisplay.textContent = opt.dataset.points || 0;
            }
        });
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
                // 更新积分显示
                const newPoints = data.new_points;
                if (isTeacher) {
                    // 教师模式：更新当前学生积分显示
                    if (studentPointsDisplay) {
                        studentPointsDisplay.textContent = newPoints;
                    }
                    // 同时更新下拉框中对应选项的 data-points 和显示文本
                    if (studentSelect) {
                        const selectedOpt = studentSelect.options[studentSelect.selectedIndex];
                        if (selectedOpt) {
                            selectedOpt.dataset.points = newPoints;
                            // 更新显示文本（保留姓名，更新积分）
                            const name = selectedOpt.text.replace(/（\d+分）$/, '');
                            selectedOpt.text = name + '（' + newPoints + '分）';
                        }
                    }
                } else {
                    // 学生自己抽：更新 pointsCurrent
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
                pointsWheel.spinTo(idx, () => {
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

    // ----- 惩罚抽奖（无需积分更新） -----
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
                penaltyWheel.spinTo(idx, () => {
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