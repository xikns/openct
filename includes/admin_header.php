<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireTeacherOrStudentAdmin();
$currentUser = currentUser();
$isTeacher = ($currentUser['role'] === 'teacher');
$isStudentAdmin = ($currentUser['role'] === 'student_admin');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>后台管理 - <?= htmlspecialchars(getConfig('website_title') ?: '班级积分') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>