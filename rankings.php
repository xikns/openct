<?php
require_once 'includes/header.php';

// 获取排序方式，默认降序（高到低）
$sort = $_GET['sort'] ?? 'desc';
if (!in_array($sort, ['asc', 'desc'])) {
    $sort = 'desc';
}
$sortOrder = ($sort === 'asc') ? 'ASC' : 'DESC';

// 查询所有学生，按积分指定顺序排列，并关联最近一条积分变动记录
$sql = "
    SELECT u.id, u.realname, u.username, u.points, u.avatar,
           pl.changed_points, pl.reason, pl.created_at AS last_update
    FROM users u
    LEFT JOIN (
        SELECT user_id, changed_points, reason, created_at
        FROM points_log
        WHERE id IN (
            SELECT MAX(id) FROM points_log GROUP BY user_id
        )
    ) pl ON u.id = pl.user_id
    WHERE u.role IN ('student','student_admin')
    ORDER BY u.points $sortOrder, u.realname ASC
";
$students = $pdo->query($sql)->fetchAll();

$rank = 1;
$prevPoints = null;
$displayIndex = 1;
?>

<style>
    /* ===== 布局容器 ===== */
    .ranking-wrapper {
        background-color: #f5f6fa;
        min-height: 80vh;
        display: flex;
        justify-content: center;
        padding: 40px 20px;
    }
    .ranking-box {
        max-width: 960px;
        width: 100%;
        background: #ffffff;
        border-radius: 16px;
        padding: 30px 35px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        transition: padding 0.2s;
    }
    .ranking-box h2 {
        font-size: 24px;
        color: #1f2937;
        margin-bottom: 28px;
        text-align: center;
        letter-spacing: 0.5px;
    }

    /* ===== 排序按钮 ===== */
    .sort-toolbar {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 25px;
    }
    .btn-sort {
        padding: 8px 22px;
        border-radius: 30px;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        color: #4b5563;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: 0.2s ease;
        white-space: nowrap;
    }
    .btn-sort:hover {
        background: #e5e7eb;
        border-color: #9ca3af;
    }
    .btn-sort.active {
        background: #4f46e5;
        color: white;
        border-color: #4f46e5;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }

    /* ===== 表格容器（滚动） ===== */
    .table-wrapper {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #f1f3f5;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        min-width: 600px;  /* 保证列宽足够，低于此宽则滚动 */
    }
    th, td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid #f1f3f5;
        vertical-align: middle;
    }
    th {
        background: #fafbfc;
        color: #6b7280;
        font-weight: 600;
        font-size: 13px;
        white-space: nowrap;
        letter-spacing: 0.3px;
    }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background-color: #fafbfc; }   /* 悬停效果 */

    .rank-number {
        font-weight: 700;
        font-size: 22px;
        color: #4f46e5;
        letter-spacing: -0.5px;
    }
    .user-avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        vertical-align: middle;
        margin-right: 12px;
        background: #e5e7eb;
    }
    .user-name {
        font-weight: 600;
        color: #1f2937;
    }
    .user-username {
        font-size: 12px;
        color: #9ca3af;
        display: block;
        margin-top: 1px;
    }
    .text-up { color: #16a34a; font-weight: 600; }
    .text-down { color: #dc2626; font-weight: 600; }
    .text-muted { color: #9ca3af; }
    .fw-bold { font-weight: 700; }

    .btn-return {
        display: inline-block;
        margin-top: 30px;
        padding: 10px 30px;
        border-radius: 30px;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
        color: #4b5563;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: 0.2s;
    }
    .btn-return:hover {
        background: #e5e7eb;
        border-color: #d1d5db;
    }

    /* ===== 响应式适配 ===== */

    /* 平板及小屏笔记本 */
    @media (max-width: 768px) {
        .ranking-box { padding: 20px 20px; }
        table { font-size: 13px; min-width: 500px; }
        th, td { padding: 10px 12px; }
        .rank-number { font-size: 19px; }
        .user-avatar-sm { width: 30px; height: 30px; margin-right: 8px; }
        .btn-sort { padding: 6px 16px; font-size: 13px; }
    }

    /* 手机横屏/大屏手机 (≤576px) */
    @media (max-width: 576px) {
        .ranking-wrapper { padding: 16px 8px; }
        .ranking-box { padding: 12px 12px; border-radius: 12px; }
        table { font-size: 12px; min-width: 440px; }
        th, td { padding: 8px 8px; }
        .rank-number { font-size: 17px; }
        .user-avatar-sm { width: 26px; height: 26px; margin-right: 6px; }
        .user-name { font-size: 13px; }
        .user-username { font-size: 10px; }
        .btn-sort { padding: 5px 12px; font-size: 12px; white-space: normal; }
        .sort-toolbar { gap: 6px; }
        /* 隐藏“变动原因”列（表头+单元格） */
        th:nth-child(5), td:nth-child(5) {
            display: none;
        }
    }

    /* 极小屏手机 (≤400px) */
    @media (max-width: 400px) {
        .ranking-box { padding: 8px 6px; }
        table { font-size: 11px; min-width: 320px; }
        th, td { padding: 6px 4px; }
        .rank-number { font-size: 15px; }
        .user-avatar-sm { width: 20px; height: 20px; margin-right: 4px; }
        .user-name { font-size: 11px; }
        .user-username { font-size: 9px; }
        .btn-sort { font-size: 10px; padding: 4px 8px; }
        /* 隐藏“更新时间”列 */
        th:nth-child(6), td:nth-child(6) {
            display: none;
        }
        /* 同时把“变动原因”也隐藏（之前已隐藏，这里再确认） */
        th:nth-child(5), td:nth-child(5) {
            display: none;
        }
        .btn-return { padding: 6px 14px; font-size: 12px; }
    }
</style>

<div class="ranking-wrapper">
    <div class="ranking-box">
        <h2>🏆 全部排名</h2>

        <!-- 排序切换 -->
        <div class="sort-toolbar">
            <a href="?sort=desc" class="btn-sort <?= $sort === 'desc' ? 'active' : '' ?>">
                积分从高到低
            </a>
            <a href="?sort=asc" class="btn-sort <?= $sort === 'asc' ? 'active' : '' ?>">
                积分从低到高
            </a>
        </div>

        <?php if (empty($students)): ?>
            <div style="text-align: center; padding: 60px 0; color: #9ca3af; font-size: 16px;">
                <span style="font-size: 40px; display: block; margin-bottom: 10px;">📭</span>
                暂无学生数据，快去添加吧！
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60px;">排名</th>
                            <th>学生</th>
                            <th>当前积分</th>
                            <th>最近变动</th>
                            <th>变动原因</th>
                            <th>更新时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <?php
                                // 并列排名处理
                                if ($prevPoints !== null && $s['points'] == $prevPoints) {
                                    // 保持排名不变
                                } else {
                                    $rank = $displayIndex;
                                    $prevPoints = $s['points'];
                                }
                                $displayIndex++;
                                // 头像路径
                                $avatarSrc = !empty($s['avatar']) ? 'uploads/avatars/' . $s['avatar'] : 'assets/default-avatar.png';
                            ?>
                            <tr>
                                <td><span class="rank-number"><?= $rank ?></span></td>
                                <td>
                                    <img src="<?= $avatarSrc ?>" 
                                         class="user-avatar-sm" 
                                         alt="头像"
                                         onerror="this.src='assets/default-avatar.png'">
                                    <span class="user-name"><?= htmlspecialchars($s['realname']) ?></span>
                                    <span class="user-username"><?= htmlspecialchars($s['username']) ?></span>
                                </td>
                                <td class="fw-bold"><?= $s['points'] ?></td>
                                <td>
                                    <?php if (isset($s['changed_points'])): ?>
                                        <span class="<?= $s['changed_points'] > 0 ? 'text-up' : 'text-down' ?>">
                                            <?= $s['changed_points'] > 0 ? '+' . $s['changed_points'] : $s['changed_points'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($s['reason'] ?? '-') ?></td>
                                <td style="color:#6b7280; font-size:0.85rem;">
                                    <?= isset($s['last_update']) ? date('Y-m-d H:i', strtotime($s['last_update'])) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="index.php" class="btn-return">← 返回首页</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>