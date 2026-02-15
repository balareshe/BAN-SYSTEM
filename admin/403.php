<?php

/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}


http_response_code(403);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 禁止访问 - 后台管理系统</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="admin-container">
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-shield-alt"></i>
                <span>ADMIN</span>
            </div>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>仪表盘</span>
            </a>
            <a href="bans.php" class="nav-item">
                <i class="fas fa-user-slash"></i>
                <span>封禁管理</span>
            </a>
            <a href="logs.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>操作日志</span>
            </a>
                        <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>系统设置</span>
            </a>
        </div>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i>
                <span>退出登录</span>
            </a>
        </div>
    </div>

    <div class="admin-main">
        <div class="admin-content">
            <div class="content-header">
                <div class="content-title">
                    <i class="fas fa-ban"></i>
                    <span>403 禁止访问</span>
                </div>
            </div>

            <div class="data-table-container">
                <div style="text-align: center; padding: 100px 20px;">
                    <i class="fas fa-lock" style="font-size: 5rem; color: #ff6b6b; margin-bottom: 30px;"></i>
                    <h2 style="color: #ff6b6b; margin-bottom: 20px;">访问被拒绝</h2>
                    <p style="color: #ccc; font-size: 1.2rem; max-width: 600px; margin: 0 auto 40px; line-height: 1.6;">
                        抱歉，您没有权限访问此页面或执行此操作。<br>
                        如果您认为这是一个错误，请联系系统管理员。
                    </p>
                    <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                        <a href="dashboard.php" class="action-button">
                            <i class="fas fa-home"></i> 返回仪表盘
                        </a>
                        <a href="bans.php" class="action-button secondary">
                            <i class="fas fa-user-slash"></i> 查看封禁列表
                        </a>
                        <button onclick="history.back()" class="action-button">
                            <i class="fas fa-arrow-left"></i> 返回上一页
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
</body>
</html>