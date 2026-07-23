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
        // 确定抽奖主体
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
?>

<div class="container py-4">
  <!-- 标签切换 -->
  <ul class="nav nav-pills mb-4 justify-content-center" id="lotteryTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="points-tab" data-bs-toggle="pill" data-bs-target="#points-lottery" type="button">积分抽奖</button>
    </li>
    <?php if ($isTeacher && $penaltyEnabled): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="penalty-tab" data-bs-toggle="pill" data-bs-target="#penalty-lottery" type="button">惩罚抽奖</button>
    </li>
    <?php endif; ?>
  </ul>

  <div class="tab-content">
    <!-- ========== 积分抽奖面板 ========== -->
    <div class="tab-pane fade show active" id="points-lottery">
      <div class="row">
        <div class="col-lg-8">
          <div class="card-apple text-center p-4">
            <h3 class="fw-semibold mb-3">🎡 积分抽奖</h3>
            <?php if ($isTeacher): ?>
            <div class="card-apple p-3 mb-4 text-start">
              <label class="form-label fw-semibold">选择抽奖学生：</label>
              <select id="pointsStudentSelect" class="form-apple w-100">
                <?php foreach ($pdo->query("SELECT id,realname,points FROM users WHERE role IN ('student','student_admin') ORDER BY realname")->fetchAll() as $stu): ?>
                  <option value="<?= $stu['id'] ?>" data-points="<?= $stu['points'] ?>"><?= htmlspecialchars($stu['realname']) ?>（<?= $stu['points'] ?>分）</option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <p class="text-accent mb-4">每次消耗 <strong><?= $lotteryCost ?></strong> 积分，当前积分：<strong id="pointsCurrent"><?= $drawUser['points'] ?></strong></p>
            <div class="position-relative d-inline-block">
              <canvas id="pointsWheel" width="400" height="400"></canvas>
              <button id="pointsSpin" class="btn-apple primary position-absolute top-50 start-50 translate-middle" style="border-radius:50%; width:80px; height:80px;">抽奖</button>
            </div>
            <div id="pointsResult" class="mt-4 fw-semibold fs-5"></div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card-apple mb-4">
            <h6 class="fw-semibold">🏆 积分排名 TOP5</h6>
            <ol class="list-unstyled"><?php foreach ($top5 as $i=>$s): ?>
              <li class="d-flex justify-content-between py-1"><span><?=$i+1?>. <?=htmlspecialchars($s['realname'])?></span><span><?=$s['points']?></span></li>
            <?php endforeach; ?></ol>
          </div>
          <a href="index.php" class="btn-apple secondary w-100">← 返回首页</a>
        </div>
      </div>
    </div>

    <!-- ========== 惩罚抽奖面板（仅教师） ========== -->
    <?php if ($isTeacher && $penaltyEnabled): ?>
    <div class="tab-pane fade" id="penalty-lottery">
      <div class="row">
        <div class="col-lg-8">
          <div class="card-apple text-center p-4">
            <h3 class="fw-semibold mb-3">😈 惩罚抽奖</h3>
            <div class="card-apple p-3 mb-4 text-start">
              <label class="form-label fw-semibold">选择受罚学生（默认积分最低<?=$penaltyCount?>人）：</label>
              <select id="penaltyStudentSelect" class="form-apple w-100">
                <?php foreach ($penaltyStudents as $stu): ?>
                  <option value="<?=$stu['id']?>"><?=htmlspecialchars($stu['realname'])?>（<?=$stu['points']?>分）</option>
                <?php endforeach; ?>
              </select>
            </div>
            <p class="text-accent mb-4">惩罚抽奖不消耗积分</p>
            <div class="position-relative d-inline-block">
              <canvas id="penaltyWheel" width="400" height="400"></canvas>
              <button id="penaltySpin" class="btn-apple primary position-absolute top-50 start-50 translate-middle" style="border-radius:50%; width:80px; height:80px;">惩罚</button>
            </div>
            <div id="penaltyResult" class="mt-4 fw-semibold fs-5"></div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- 转盘绘制逻辑（两个独立 Canvas） -->
<script>
// 积分转盘
const pointsPrizes = <?= json_encode(array_values($prizes)) ?>;
const penaltyPrizes = <?= json_encode(array_values($penaltyPrizes)) ?>;
const colors = ['#FF6B6B','#4ECDC4','#FFD93D','#6C5CE7','#A8E6CF','#FF8C42','#45B7D1','#F9CA24','#FF7979','#BADC58'];

function createWheel(canvasId, prizes) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    let startAngle = 0;

    function draw(highlightIndex = -1) {
        const cx = canvas.width/2, cy = canvas.height/2, r = 180;
        const slice = (2*Math.PI) / prizes.length;
        ctx.clearRect(0,0,canvas.width,canvas.height);
        for (let i=0; i<prizes.length; i++) {
            const angle = startAngle + i*slice;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, r, angle, angle+slice);
            ctx.fillStyle = (highlightIndex===i) ? '#FFD700' : colors[i % colors.length];
            ctx.fill();
            ctx.strokeStyle='#fff'; ctx.lineWidth=2; ctx.stroke();
            ctx.save();
            ctx.translate(cx,cy);
            ctx.rotate(angle+slice/2);
            ctx.fillStyle='#000';
            ctx.font='bold 14px sans-serif';
            ctx.textAlign='right';
            ctx.fillText(prizes[i].name.length>8?prizes[i].name.substring(0,6)+'..':prizes[i].name, r-20, 5);
            ctx.restore();
        }
    }

    function spinTo(prizeIndex, callback) {
        const slice = (2*Math.PI) / prizes.length;
        const targetAngle = (prizeIndex + 0.5) * slice;
        const total = 2*Math.PI*5 + (2*Math.PI - targetAngle - startAngle % (2*Math.PI));
        const duration = 4000, startTime = performance.now(), initial = startAngle;
        function anim(now) {
            const p = Math.min((now-startTime)/duration, 1);
            const ease = 1 - Math.pow(1-p, 3);
            startAngle = initial + total * ease;
            draw();
            if (p<1) requestAnimationFrame(anim);
            else { startAngle = initial + total; draw(prizeIndex); if(callback) callback(); }
        }
        requestAnimationFrame(anim);
    }

    draw();
    return { draw, spinTo, getAngle: ()=>startAngle };
}

// 初始化两个转盘
const pointsWheel = createWheel('pointsWheel', pointsPrizes);
const penaltyWheel = createWheel('penaltyWheel', penaltyPrizes);

// 积分抽奖逻辑
document.getElementById('pointsSpin')?.addEventListener('click', function() {
    if (this.disabled) return;
    this.disabled = true;
    document.getElementById('pointsResult').innerHTML = '抽奖中...';
    let body = 'action=draw';
    <?php if ($isTeacher): ?>
    body += '&target_user_id=' + document.getElementById('pointsStudentSelect').value;
    <?php endif; ?>
    fetch('lottery.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(r=>r.json()).then(d=>{
        if (d.error) { document.getElementById('pointsResult').innerHTML = `<span class="text-danger">${d.error}</span>`; this.disabled=false; return; }
        document.getElementById('pointsCurrent').innerText = d.new_points;
        const idx = pointsPrizes.findIndex(p=>p.name===d.prize);
        pointsWheel.spinTo(idx, ()=>{
            document.getElementById('pointsResult').innerHTML = `🎉 恭喜获得：${d.prize}！`;
            this.disabled = (d.new_points < <?=$lotteryCost?>);
            if (!this.disabled) this.textContent = '再抽一次';
        });
    }).catch(()=>{ document.getElementById('pointsResult').innerHTML='<span class="text-danger">网络错误</span>'; this.disabled=false; });
});

// 惩罚抽奖逻辑
document.getElementById('penaltySpin')?.addEventListener('click', function() {
    if (this.disabled) return;
    this.disabled = true;
    document.getElementById('penaltyResult').innerHTML = '抽奖中...';
    const targetId = document.getElementById('penaltyStudentSelect').value;
    fetch('lottery.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: `action=penalty_draw&target_user_id=${targetId}`})
    .then(r=>r.json()).then(d=>{
        if (d.error) { document.getElementById('penaltyResult').innerHTML = `<span class="text-danger">${d.error}</span>`; this.disabled=false; return; }
        const idx = penaltyPrizes.findIndex(p=>p.name===d.prize);
        penaltyWheel.spinTo(idx, ()=>{
            document.getElementById('penaltyResult').innerHTML = `😈 ${d.target_name} 获得了：${d.prize}`;
            this.disabled = false;
            this.textContent = '再惩罚';
        });
    }).catch(()=>{ document.getElementById('penaltyResult').innerHTML='<span class="text-danger">网络错误</span>'; this.disabled=false; });
});
</script>

<?php include 'includes/footer.php'; ?>