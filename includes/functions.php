<?php
// includes/functions.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function getConfig($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT config_value FROM config WHERE config_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['config_value'] : null;
}

function isTeacher() {
    $user = currentUser();
    return $user && $user['role'] === 'teacher';
}

function isStudentAdmin() {
    $user = currentUser();
    return $user && $user['role'] === 'student_admin';
}

function isTeacherOrStudentAdmin() {
    return isTeacher() || isStudentAdmin();
}

// 后台权限检查
function requireTeacher() {
    requireLogin();
    if (!isTeacher()) {
        die('无权访问');
    }
}

function requireTeacherOrStudentAdmin() {
    requireLogin();
    if (!isTeacherOrStudentAdmin()) {
        die('无权访问');
    }
}
function lotteryEnabled() {
    return getConfig('lottery_enabled') == '1';
}
function getMaxDraws() {
    return (int)(getConfig('lottery_max_times') ?: 3);
}
function getUserDrawCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lottery_records WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}